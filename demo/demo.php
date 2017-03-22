<?php
use Captcha\CaptchaBuilder;

$captcha = new CaptchaBuilder;
$captcha->build()->save('out.jpg');
