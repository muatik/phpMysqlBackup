<?
/*
MAILMAN 0.1
---------------------------
Tarih: 14 Ağustos(08) 2006
Yazan: Mustafa Atik
E-Posta: mr.mustafaatik@gmail.com
Detaylı Bilgi: http://cookingthecode.com/

Bu uygulama, php'nin mail() fonsiyonunu kullanmadan, smtp sunucusuna soket bağlantısı kurarak e-posta mesajı gönderir.
En önemli özelliği, e-posta mesajına iliştirilmiş dosyalar gönderilebilmesine imkan vermesidir.

Bu uygulamayı istediğiniz şekilde kullanabilirsiniz. İyi kullanın.

#DIKKAT#
Bu uygulama henüz tamamlanmamıştır fakat aciliyet nedeniyle bu haliyle kullanılmaktadır. 
Düzeltilmesi gereken bazı kısımları vardır.
1- boundary random türetilecek.
2- send kısmında response lar alınmadan denennecek
3- @fclose($fs); olmayacak
4- '@' ile hataların kontrolünden vazgeçmelidir.
5- kısayol metodu yazılmalıdır örn:   qSend($from,$to,$subject,$msg,$extHedars,$files) gibi.

*/
require_once('mimeType.php');
class mailman
{
	var $host='127.0.0.1';
	var $port='25';
	var $headers;
	var $from;
	var $from_full_name='';
	var $to;
	var $content_type='text/plain; charset=utf-8; format=flowed';
	var $subject;
	var $content;
	var $boundary;
	var $files;
	var $error_code;
	var $error;
	var $user_headers='';
	var $body=null;
	
	function error_throw($error_code,$ext_str='')
	{
		$this->error_code=$error_code;
		switch($error_code)
		{
			case 1:$this->error='SMTP sunucusuna bağlanılamadı.'.$ext_str;break;
			case 3:$this->error='SMTP sunucusuna veri gönderilirken hata oluştu.'.$ext_str;break;
			case 2:$this->error='SMTP sunucusundan veri okunurken hata oluştu.'.$ext_str;break;
			case 10:$this->error=$ext_str.' isimli dosya açılamıyor. ';break;
			case 11:$this->error=$ext_str.' isimli dosya okunamıyor. ';break;
			default:$this->error='Belirsiz bir hata oluştu. '.$ext_str;break;
		}
		return true;
	}
	
	function file_read($file_name)
	{
		if($fp=@fopen($file_name,'r'))
		{
			if($data=@fread($fp,filesize($file_name))) return $data;
			$this->error_throw(11,$file_name);
		}else $this->error_throw(10,$file_name);
		return false;
	}
	
	function prepare()
	{
		if(($result=$this->prepare_headers())===true)	return prepare_body();
	}
	
	function prepare_headers($call=true)
	{
		if($this->from_full_name=='') $this->from_full_name=$this->from;
		$this->headers="From: \"".$this->from_full_name."\" <".$this->from.">\r\n";
		$this->headers.="To: ".$this->to."\r\n";
		$this->headers.="Date: ".date('D, d M Y H:i:s')."\r\n";
		$this->headers.="Subject: ".$this->subject."\r\n";
		$this->headers.="Reply-To:  ".$this->from."\r\n";
		$this->headers.="Return-Path: ".$this->from."\r\n";
		if(trim($this->user_headers)!='') $this->headers.=$this->user_headers."\r\n";
		if(count($this->files)>0)
		{
			$this->boundary="--bndry0123";
			$this->headers.="MIME-Version: 1.0\r\n";
			$this->headers.="Content-Type: multipart/mixed; boundary=".$this->boundary."\r\n";
			$this->headers.="\r\nThis is multi-part message\r\n";
			if($call) $this->prepare_body();
		}
		else
		{
			$this->headers.="Content-Type: ".$this->content_type."\r\n";
			$this->headers.="Content-Transfer-Encoding: 8bit\r\n";
		}
		$this->headers.="\r\n";
		return true;
	}
	
	function prepare_body()
	{
		if($this->headers=='') $this->prepare_headers(false);
		if(count($this->files)>0) //attachment mail
		{
			$this->body="--".$this->boundary."\r\n";
			$this->body.="Content-type: ".$this->content_type."\r\n";
			$this->body.="Content-transfer-encoding: 7bit\r\n\r\n";
			$this->body.=$this->content."\r\n\r\n";
			foreach($this->files as $file)
			{
				if(($data=$this->file_read($file))!==false)
				{
					//$mimeType=mime_content_type($file) // ilgili kütüpane yüklü olmadığından etkisiz hale getirildi.
					$mimeType=getMimeType($file); // üsteki satır yerine benim yazdığım bir fonksiyon.
					$file=explode('/',$file);$file=$file[count($file)-1];
					$this->body.="--".$this->boundary."\r\n";
					$this->body.="Content-type: ".$mimeType.";name=\"".$file."\"\r\n";
					$this->body.="Content-transfer-encoding: base64\r\n";
					$this->body.="Content-disposition: attachment;filename=\"".$file."\"\r\n\r\n";
					$this->body.=chunk_split(base64_encode($data));
				}
			}
		}
		else //simple text mail
		{
			$this->body=$this->content;
		}
		$this->body.="\r\n.\r\n";
		return true;
	}
	function send()
	{
		if($this->headers=='' || $this->body=='') $this->prepare_body();
		if(!$fs=fsockopen($this->host,$this->port)) {$this->error_throw(1); return false;}
		if(!$response=@fgets($fs,128)) {@fclose($fs);$this->error_throw(3);return false;}
		if(!@fwrite($fs,"HELO ".$this->host."\n")) {@fclose($fs);$this->error_throw(2);return false;}
		if(!$response=@fgets($fs,128)) {@fclose($fs);$this->error_throw(3);return false;}
		if(!@fwrite($fs,"MAIL From: ".$this->from."\n")) {@fclose($fs);$this->error_throw(2);return false;}
		if(!$response=@fgets($fs,128)) {@fclose($fs);$this->error_throw(3);return false;}
		if(!@fwrite($fs,"RCPT To: ".$this->to."\n")) {@fclose($fs);$this->error_throw(2);return false;}
		if(!$response=@fgets($fs,128)) {@fclose($fs);$this->error_throw(3);return false;}
		if(!@fwrite($fs,"DATA\n")) {@fclose($fs);$this->error_throw(2);return false;}
		if(!$response=@fgets($fs,128)) {@fclose($fs);$this->error_throw(3);return false;}
		if(!@fwrite($fs,$this->headers.$this->body)){@fclose($fs);$this->error_throw(2);return false;}
		if(!$response=@fgets($fs,128)) {@fclose($fs);$this->error_throw(3);return false;}
		fclose($fs);
		if(substr($response,0,3)==250) return true;
		return $response;
	}
}

function send_email($to,$subject,$content,$from='bilgi@Kocaelide.com',$full_name='Kocaelide.com')
{
	$p=new mailman();
	$p->content_type='text/plain; charset=iso-8859-9; format=flowed';
	$p->from=mb_convert_encoding($from,'ISO-8859-9','UTF-8');
	$p->from_full_name=mb_convert_encoding($full_name,'ISO-8859-9','UTF-8');
	$p->to=mb_convert_encoding($to,'ISO-8859-9','UTF-8');
	$p->subject=mb_convert_encoding($subject,'ISO-8859-9','UTF-8');
	$p->content=mb_convert_encoding($content,'ISO-8859-9','UTF-8');
	return $p->send();
}
?>