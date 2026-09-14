<?php
declare(strict_types=1);
namespace LGFC;
use Closure;
use DomainException;

final class MovieMetadata
{
    public function __construct(private string $token, private ?Closure $transport = null) {}

    public static function configured(): self
    {
        $path=dirname(__DIR__).'/var/tmdb-token.txt';
        $token=is_file($path)?trim((string)file_get_contents($path)):'';
        return new self($token);
    }

    public function available(): bool
    {
        return $this->token !== '' && strlen($this->token) <= 4096 && preg_match('/^[A-Za-z0-9._-]+$/D',$this->token)===1;
    }

    private static function text(mixed $value,int $limit): string
    {
        if (!is_string($value) || !preg_match('//u',$value)) return '';
        $value=trim($value);
        if(strlen($value)>$limit) {
            $value=substr($value,0,$limit);
            while(!preg_match('//u',$value)) $value=substr($value,0,-1);
        }
        return $value;
    }

    public function search(string $query,string $year=''): array
    {
        $query=trim($query);$year=trim($year);
        if($query==='' || strlen($query)>200 || preg_match('/[\x00-\x1f\x7f]/',$query)) throw new DomainException('Enter a film title of at most 200 characters.');
        if($year!=='' && (!ctype_digit($year) || (int)$year<1888 || (int)$year>2100)) throw new DomainException('Enter a valid release year.');
        if(!$this->available()) throw new DomainException('Movie search is not configured yet. You can enter the film manually.');
        $params=['query'=>$query,'language'=>'en-GB','include_adult'=>'false','page'=>1];
        if($year!=='')$params['primary_release_year']=$year;
        $url='https://api.themoviedb.org/3/search/movie?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
        $headers=['Accept: application/json','Authorization: Bearer '.$this->token];
        if($this->transport) { $response=($this->transport)($url,$headers); }
        else { $response=self::request($url,$headers); }
        if($response['status']===429) throw new DomainException('Movie search is busy. Try again shortly, or enter the film manually.');
        if(in_array($response['status'],[401,403],true)) throw new DomainException('Movie search could not authenticate. Ask an organiser to check the TMDB setup.');
        if($response['status']!==200) throw new DomainException('Movie search is unavailable. Try again later, or enter the film manually.');
        try {$data=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);}
        catch(\JsonException $error){throw new DomainException('Movie search returned an unreadable response. Try again later.');}
        if(!is_array($data['results']??null)) throw new DomainException('Movie search returned an unexpected response. Try again later.');
        $results=[];
        foreach(array_slice($data['results'],0,20) as $row) {
            if(!is_array($row) || !is_int($row['id']??null) || $row['id']<1 || ($row['adult']??false)===true)continue;
            $title=self::text($row['title']??'',300);
            if($title==='')continue;
            $date=$row['release_date']??'';$releaseYear='';
            if(is_string($date) && preg_match('/^(\d{4})-\d{2}-\d{2}$/D',$date,$match) && (int)$match[1]>=1888 && (int)$match[1]<=2100) $releaseYear=$match[1];
            $poster=$row['poster_path']??null;
            $image=is_string($poster) && preg_match('~^/[A-Za-z0-9_-]+\.(jpg|png|webp)$~D',$poster)?'https://image.tmdb.org/t/p/w500'.$poster:'';
            $results[]=['id'=>$row['id'],'title'=>$title,'year'=>$releaseYear,'summary'=>self::text($row['overview']??'',8000),'image_url'=>$image];
        }
        return $results;
    }

    private static function request(string $url,array $headers): array
    {
        if(!function_exists('curl_init'))throw new DomainException('Movie search is unavailable on this server. You can enter the film manually.');
        $curl=curl_init($url);$body='';
        curl_setopt_array($curl,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int {
                if(strlen($body)+strlen($chunk)>1048576)return 0;
                $body.=$chunk;return strlen($chunk);
            }]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($ok===false)throw new DomainException('Movie search could not connect. Try again later, or enter the film manually.');
        return ['status'=>$status,'body'=>$body];
    }
}
