<?php
declare(strict_types=1);
namespace App\Support;
final class Jalali {
 public static function toGregorian(string $date): string
 {
  $parts = preg_split('/[\\/-]/', trim($date));
  if (count($parts) !== 3) {
   throw new InvalidArgumentException('Invalid Jalali date.');
  }

  [$jy, $jm, $jd] = array_map('intval', $parts);
  if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > ($jm <= 6 ? 31 : ($jm <= 11 ? 30 : 30))) {
   throw new InvalidArgumentException('Invalid Jalali date.');
  }

  $jy -= 979;
  $jDayNo = 365 * $jy
      + intdiv($jy, 33) * 8
      + intdiv(($jy % 33) + 3, 4)
      + $jd - 1
      + ($jm < 7 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186);

  $gDayNo = $jDayNo + 79;
  $gy = 1600 + 400 * intdiv($gDayNo, 146097);
  $gDayNo %= 146097;

  if ($gDayNo >= 36525) {
   $gDayNo--;
   $gy += 100 * intdiv($gDayNo, 36524);
   $gDayNo %= 36524;
   if ($gDayNo >= 365) {
    $gDayNo++;
   }
  }

  $gy += 4 * intdiv($gDayNo, 1461);
  $gDayNo %= 1461;

  if ($gDayNo > 365) {
   $gy += intdiv($gDayNo - 1, 365);
   $gDayNo = ($gDayNo - 1) % 365;
  }

  $gd = $gDayNo + 1;
  $daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  $leap = ($gy % 4 === 0 && ($gy % 100 !== 0 || $gy % 400 === 0));
  $gm = 1;

  foreach ($daysInMonth as $days) {
   if ($gm === 2 && $leap) {
    $days++;
   }
   if ($gd <= $days) {
    break;
   }
   $gd -= $days;
   $gm++;
  }

  return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
 }
 public static function fromGregorian(string $date):string{[$gy,$gm,$gd]=array_map('intval',explode('-',substr($date,0,10)));$dm=[0,31,28,31,30,31,30,31,31,30,31,30,31];$gy2=$gy-1600;$gm2=$gm-1;$gd2=$gd-1;$days=365*$gy2+intdiv($gy2+3,4)-intdiv($gy2+99,100)+intdiv($gy2+399,400);for($i=0;$i<$gm2;$i++)$days+=$dm[$i+1];if($gm2>1&&($gy%4===0&&($gy%100!==0||$gy%400===0)))$days++;$days+=$gd2-79;$jy=979+33*intdiv($days,12053);$days%=12053;$jy+=4*intdiv($days,1461);$days%=1461;if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}$jm=$days<186?1+intdiv($days,31):7+intdiv($days-186,30);$jd=1+($days<186?$days%31:($days-186)%30);return sprintf('%04d/%02d/%02d',$jy,$jm,$jd);}
}
