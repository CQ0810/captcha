<?php
/**
 * User: macro chen <chen_macro@163.com>
 * Date: 17-3-20
 * Time: 下午1:52
 */

namespace Captcha;

use Captcha\Interfaces\CaptchaValidateInterface;
use Doctrine\Common\Cache\Cache;

class CaptchaValidate implements CaptchaValidateInterface
{
    /**
     * @var Cache
     */
    private $cache;

    /**
     * @param $phrase
     * @param Cache|null $cache
     * @return boolean
     */
    public function validate($phrase, Cache $cache = null)
    {
        $cache === null ? $this->cache = app()->component('captcha_cache') : $this->cache = $cache;
        $id = md5($phrase);
        if ($this->cache->fetch($id)) {
            $this->cache->delete($id);
            return true;
        }
        return false;
    }
}