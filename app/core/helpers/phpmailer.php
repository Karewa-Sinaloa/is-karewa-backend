<?php
namespace App\Helpers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

abstract class ApiMailer {
	/**
	 * Seam for the mail transport. Defaults to a new PHPMailer instance; tests
	 * may replace it to avoid the network. Kept public on purpose for that
	 * override.
	 * @var callable|null
	 */
	public static $transport = null;

	static public function Send(Array $params) {
		global $_config;
		$mailing = (object) (($_config ?? NULL)?->mailing ?? []);
		$mail = self::$transport !== NULL
			? call_user_func(self::$transport, true)
			: new PHPMailer(true);
		try {
			//Server settings, read from the `mailing` configuration section
			$mail->SMTPDebug  = (int) ($mailing->debug ?? 0);
			$mail->isSMTP();
			$mail->Host       = $mailing->host ?? '';
			$mail->SMTPAuth   = (bool) ($mailing->smtp_auth ?? false);
			$mail->Username   = $mailing->user ?? '';
			$mail->Password   = $mailing->password ?? '';
			$mail->SMTPSecure = $mailing->security ?? '';
			$mail->Port       = (int) ($mailing->port ?? 25);
			$mail->CharSet    = 'UTF-8';
			//Recipients
			$mail->setFrom($params['from']['email'], $params['from']['name'] ?? '');
			foreach($params['to'] as $recipient) {
				$mail->addAddress($recipient['email'], $recipient['name'] ?? '');
			}
			if(!empty($params['reply_to']) && is_array($params['reply_to'])) {
				$mail->addReplyTo($params['reply_to']['email'], $params['reply_to']['name'] ?? '');
			}
			if(!empty($params['cc']) && is_array($params['cc'])) {
				foreach($params['cc'] as $recipient) {
					$mail->addCC($recipient['email']);
				}
			}
			if(!empty($params['bcc']) && is_array($params['bcc'])) {
				foreach($params['bcc'] as $recipient) {
					$mail->addBCC($recipient['email']);
				}
			}

			//Attachments
			if(!empty($params['attachments']) && is_array($params['attachments'])) {
				foreach($params['attachments'] as $attachment) {
					$mail->addAttachment($attachment['file'], $attachment['name'] ?? '');
				}
			}

			//Content
			$mail->isHTML(true);
			$mail->Subject = $params['subject'];
			$mail->Body    = $params['html_body'];
			$mail->AltBody = $params['text_body'] ?? '';

			$mail->send();
		} catch (\Exception $e) {
			$mailer_error  = $mail->ErrorInfo ?? $e->getMessage();
			$params_string = json_encode($params);
			throw new \AppException("Message could not be sent. Mailer Error: {$mailer_error} {$params_string}", 903000);
		}
	}
}
?>
