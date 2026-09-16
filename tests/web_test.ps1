param([string]$Php = 'php')
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
$temp=Join-Path ([IO.Path]::GetTempPath()) ('lgfc-web-test-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp | Out-Null
$auth=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes((Join-Path $root 'src/Auth.php')))
$web=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes((Join-Path $root 'src/Web.php')))
$fixture='<?php require base64_decode("AUTH_PATH"); require base64_decode("WEB_PATH"); session_save_path(__DIR__); LGFC\Web::start(); header("Content-Type: application/json"); if ($_SERVER["REQUEST_METHOD"] === "POST") { LGFC\Web::checkCsrf($_POST["csrf"] ?? "", true); echo json_encode(["ok"=>true]); } else { echo json_encode(["csrf"=>$_SESSION["csrf"]]); }'
$fixture.Replace('AUTH_PATH',$auth).Replace('WEB_PATH',$web) | Set-Content (Join-Path $temp 'index.php')
$listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,0)
$listener.Start();$port=$listener.LocalEndpoint.Port;$listener.Stop()
$log=Join-Path $temp 'error.log'
$options=@{FilePath=$Php;ArgumentList=@('-S',"127.0.0.1:$port");WorkingDirectory=$temp;PassThru=$true;RedirectStandardError=$log;RedirectStandardOutput=(Join-Path $temp 'output.log')}
if($IsWindows){$options.WindowStyle='Hidden'}
$server=Start-Process @options
function Check($condition,$label){if(!$condition){throw $label};Write-Output "PASS - $label"}
try {
    $url="http://127.0.0.1:$port/"
    $ready=$false
    for($i=0;$i -lt 30;$i++){
        try {$page=Invoke-WebRequest $url -SessionVariable browser;$ready=$true;break} catch {Start-Sleep -Milliseconds 100}
    }
    if(!$ready){throw 'Test server did not start'}
    $token=($page.Content|ConvertFrom-Json).csrf
    $page2=Invoke-RestMethod $url -WebSession $browser
    Check ($page2.csrf -eq $token) 'session token persists across page loads'
    $ok=Invoke-RestMethod $url -Method Post -WebSession $browser -Body @{csrf=$token}
    Check $ok.ok 'valid request remains accepted'
    $bad=Invoke-WebRequest $url -Method Post -WebSession $browser -Body @{csrf='test-secret-do-not-log'} -SkipHttpErrorCheck
    $errorBody=$bad.Content|ConvertFrom-Json
    Check ($bad.StatusCode -eq 403 -and $errorBody.code -eq 'csrf_failed' -and $errorBody.reference -match '^[a-f0-9]{12}$') 'mismatch returns 403 and a support reference'
    $missing=Invoke-WebRequest $url -Method Post -WebSession $browser -Body @{} -SkipHttpErrorCheck
    Check ($missing.StatusCode -eq 403) 'missing token is rejected'
    $lost=Invoke-WebRequest $url -Method Post -Body @{csrf=$token} -SkipHttpErrorCheck
    Check ($lost.StatusCode -eq 403) 'lost session does not bypass protection'
    $entries=@(Get-Content $log | Where-Object {$_ -match 'LGFC \{'} | ForEach-Object {($_ -replace '^.*LGFC ','')|ConvertFrom-Json})
    $match=@($entries | Where-Object reference -eq $errorBody.reference)[0]
    Check ($match.reason -eq 'csrf_token_mismatch' -and $match.session_cookie_present -and !$match.csrf_created_this_request) 'reference identifies a mismatch in a retained session'
    $last=$entries[-1]
    Check (!$last.session_cookie_present -and $last.csrf_created_this_request) 'missing session is distinguishable in diagnostics'
    $logText=Get-Content $log -Raw
    Check (!$logText.Contains($token) -and !$logText.Contains('test-secret-do-not-log')) 'tokens are absent from logs'
    foreach($cookie in $browser.Cookies.GetCookies([uri]$url)){Check (!$logText.Contains($cookie.Value)) 'session credentials are absent from logs'}
    $ok=Invoke-RestMethod $url -Method Post -WebSession $browser -Body @{csrf=$token}
    Check $ok.ok 'failed request does not invalidate the existing session'
} finally {if(!$server.HasExited){Stop-Process -Id $server.Id};$server.Dispose()}
