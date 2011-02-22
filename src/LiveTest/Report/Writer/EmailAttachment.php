<?php

namespace LiveTest\Report\Writer;

use LiveTest\Exception;

/**
 * This writer is used to attach a formatted text to an e-mail and sends it.
 * 
 * @author Nils Langner
 */
class EmailAttachment implements Writer
{
  private $emailTemplate = 'templates/email_attachment.tpl';
  
  private $to;
  private $attachmentName = 'LiveTest Report';
  private $from;
  private $subject;
  
  /**
   * Sets the e-mail parameters
   * 
   * @param email $to
   * @param email $from
   * @param string $subject
   * @param string $attachmentName
   * @param string $emailTemplate
   */
  public function init($to, $from, $subject, $attachmentName = null, $emailTemplate = null)
  {
    $this->to = $to;
    $this->from = $from;
    $this->subject = $subject;
    
    if (!is_null($attachmentName))
    {
      $this->attachmentName = $attachmentName;
    }
    
    if (!is_null($emailTemplate))
    {
      $this->emailTemplate = $emailTemplate;
    }
    else
    {
      $this->emailTemplate = __DIR__ . '/' . $this->emailTemplate;
    }
  }
  
  /**
   * E-mails the formatted text as attachment.
   * 
   * @param string $formatedText
   */
  public function write($formatedText)
  {
    $mail = new \Zend_Mail();
    
    $mail->addTo($this->to);
    $mail->setFrom($this->from);
    $mail->setSubject($this->subject);
    
    $mail->setBodyHtml(file_get_contents($this->emailTemplate));
    
    $at = new \Zend_Mime_Part($formatedText);
    $at->type = 'text/html';
    $at->disposition = \Zend_Mime::DISPOSITION_INLINE;
    $at->encoding = \Zend_Mime::ENCODING_BASE64;
    $at->filename = $this->attachmentName;
    $at->description = 'LiveTest Attachment';
    
    $mail->addAttachment($at);
    
    $mail->send();
  }
}