<?php
$a=array(
	'host'=>'localhost',
	'user'=>'root',
	'password'=>'root',
	'dbs'=>array('bot_fnpublic','food'),
	'storePath'=>'/var/www/localhost/phpMysqlBackup/tmp/'
);

class pmysqlb
{
	/**
	 * linux komudunu çalıştırır
	 * @param string $c
	 * @return str komudun stdout çıktısı 
	 * */
	public function runc($c){
		return shell_exec($c);
	}
	
	/**
	 * verilen parametre setine göre yedek alır.
	 * @param string $s
	 * @return string komudunu stdout çıktısı
	 * */
	public function backup($s){

		// kaydedilecek dosyanın adı
		$fileName=implode('_',$s['dbs']).date('Ymd');

		// mysqldump ifadesi
		$dumpc=sprintf(
			'mysqldump -h%s -u%s -p%s --databases %s',
			$s['host'],$s['user'],$s['password'],implode(' ',$s['dbs'])
		);

		// dosyanın kaydetme yöntemi
		$toFile='bzip2 > '.$fileName.'.bz2';
		
		return $this->runc($dumpc.' | '.$toFile);

	}
}

$x=new pmysqlb();
$x->backup($a);
?>
