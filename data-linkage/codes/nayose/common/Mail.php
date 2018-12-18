<?php
require_once('lib/class.phpmailer.php');

class Mail{
	private $mail, $logger;

	/**
	 * mail class constructor
	 * @param int $procNo process no
	 */
	function __construct(){
		global $procNo;
		try{
			$this->logger = new Logger($procNo);
			$this->mail = new PHPMailer();
			$this->mail->Host = 'smtp.gmail.com';
			$this->mail->SMTPSecure = 'tls';
			$this->mail->Port = 587;
		}catch(Exception $e){
			$this->logger->error("Mailer Error: " . $e->getMessage());
		}
	}

	/**
	 * send mail
	 * @param string $body email content
	 */
	function sendMail($message = ""){
		global $MAIL, $procNo;
		try{
			$this->mail->Encoding = "7bit";// 日本語対応
			$this->mail->CharSet = 'ISO-2022-JP';// 日本語対応
			//$this->mail->isHTML(true);
			$this->mail->SetFrom($MAIL['from']);
			$this->mail->AddAddress($MAIL[$procNo]['to']);
			$this->mail->Subject= $MAIL[$procNo]['subject'];
			$this->mail->Body= mb_convert_encoding($MAIL[$procNo]['body']."\r\n".$message, "JIS", "UTF-8"); // 日本語対応 メールが送られる原因となったメッセージを追記
			if(!$this->mail->Send()) {
				$this->logger->error("Mailer Error: " . $this->mail->ErrorInfo);
			} else {
				$this->logger->info("Message sent!");
			}
		}catch(Exception $e){
			$this->logger->error("Mailer Error: " . $e->getMessage());
		}
	}
}
?>