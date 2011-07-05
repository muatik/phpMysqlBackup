<?php
$this->schedules=array(
	'2880'=>array(
		'db'=>array(
			'host'=>'localhost',
			'user'=>'root',
			'password'=>'root'
		),
		'dbObjects'=>array(
			'bot_fnpublic'=>array('*')
		),
		//'email'=>array('to'=>'mr.atik@gmail.com','title'=>'CookinTC DB Yedeği - ','from'=>'bilgi@cookingthecode.com','fileName'=>'cookingVtYedek','timeSuffix'=>true,'titleTimeSuffix'=>true,'message'=>'Cookingthecode.com Veritabanı Yedeğidir. Lak lak lak...'),
		'local'=>array('fileName'=>'fnpublicDB','timeSuffix'=>true),
		//'ftp'=>array(
		//	'address'=>'site.com',
		//	'username'=>'ftp_user',
		//	'password'=>'ftp_password',
		//	'remoteDir'=>'/public_html/',
		//	'fileName'=>'aaYedek',
		//	'timeSuffix'=>true
		//)
	)
);

// yedek dizini
$this->tmpDir='/var/www/dbs';

?>
