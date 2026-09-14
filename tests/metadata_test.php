<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\MovieMetadata;
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS - {$label}\n";}
function rejects(callable $fn,string $label):void{try{$fn();}catch(DomainException $error){check(true,$label);return;}throw new RuntimeException($label);}
$called=0;$url='';$headers=[];
$service=new MovieMetadata('private-test-token',function($request,$requestHeaders)use(&$called,&$url,&$headers):array{
 $called++;$url=$request;$headers=$requestHeaders;
 return ['status'=>200,'body'=>json_encode(['results'=>[
  ['id'=>10,'title'=>'Test title','release_date'=>'1980-01-01','overview'=>'Test synopsis','poster_path'=>'/poster.jpg','adult'=>false,'privateExtra'=>'Do not forward'],
  ['id'=>11,'title'=>'Other release','release_date'=>'','poster_path'=>null],
  ['id'=>12,'title'=>'Unsafe poster','poster_path'=>'/../../secret.svg'],
  ['id'=>13,'title'=>'Excluded adult','adult'=>true],
  ['id'=>'bad','title'=>'Malformed'],
 ]])];
});
rejects(fn()=> (new MovieMetadata(''))->search('Film'),'missing credential fails safely');
check(!(new MovieMetadata("token\r\nInjected: header"))->available(),'credential cannot inject headers');
rejects(fn()=> $service->search(''),'empty search rejected');
rejects(fn()=> $service->search('Film','not-year'),'invalid year rejected');
check($called===0,'invalid input makes no provider request');
$results=$service->search('A & B / film','1980');
check(str_starts_with($url,'https://api.themoviedb.org/3/search/movie?') && str_contains($url,'query=A%20%26%20B%20%2F%20film') && str_contains($url,'primary_release_year=1980'),'search encodes title and year on fixed provider endpoint');
check(!str_contains($url,'private-test-token') && in_array('Authorization: Bearer private-test-token',$headers,true),'credential is sent only in authorization header');
check(count($results)===3,'malformed and adult entries filtered');
check($results[0]['year']==='1980' && $results[0]['image_url']==='https://image.tmdb.org/t/p/w500/poster.jpg','year and HTTPS poster normalized');
check($results[1]['year']==='' && $results[1]['image_url']==='' && $results[1]['summary']==='','missing metadata maps to empty editable fields');
check($results[2]['image_url']==='','unsafe poster path rejected');
check(!str_contains(json_encode($results),'privateExtra'),'provider fields are whitelisted');
foreach([401,403,429,500] as $status){$bad=new MovieMetadata('token',fn()=>['status'=>$status,'body'=>'private provider error']);rejects(fn()=> $bad->search('Film'),'provider status '.$status.' handled');}
foreach(['not json','{}'] as $body){$bad=new MovieMetadata('token',fn()=>['status'=>200,'body'=>$body]);rejects(fn()=> $bad->search('Film'),'malformed provider response handled');}
$unicode=new MovieMetadata('token',fn()=>['status'=>200,'body'=>json_encode(['results'=>[['id'=>1,'title'=>str_repeat('é',200),'overview'=>str_repeat('é',5000)]]])]);
$row=$unicode->search('Film')[0];check(strlen($row['title'])<=300 && strlen($row['summary'])<=8000 && preg_match('//u',$row['summary'])===1,'provider text bounded without breaking UTF-8');
