<?php
/**
 * User: macro chen <chan_macro@163.com>
 * Date: 17-3-20
 * Time: 下午1:53
 */
namespace Captcha\Interfaces;

use Doctrine\Common\Cache\Cache;

interface CaptchaValidateInterface
{
    /**
     * @param $phrase
     * @param Cache|null $cache
     * @return boolean
     */
    public function validate($phrase, Cache $cache = null);
}