<?php
namespace Component\Captcha\Interfaces;

/**
 * Interface for the PhraseBuilder
 *
 * @author macro chen  <chen_macro@163.com>
 */
interface PhraseBuilderInterface
{
    /**
     * Generates  random phrase of given length with given charset
     */
    public function build($length, $charset);

    /**
     * "Niceize" a code
     */
    public function niceize($str);
}
