<?php
declare(strict_types=1);
namespace App\Services;
final class MailerService{
 public static function send(string $to,string $subject,string $body):bool{
  $from=(string)env('MAIL_FROM','no-reply@localhost');$headers="From: ".$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n";
  return mail($to,$subject,$body,$headers);
 }
}