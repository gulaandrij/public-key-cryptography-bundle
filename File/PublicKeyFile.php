<?php
/**
 * Copyright (c) 2017 DarkWeb Design
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace DarkWebDesign\PublicKeyCryptographyBundle\File;

use DarkWebDesign\PublicKeyCryptographyBundle\Exception\FormatNotValidException;
use Symfony\Component\Process\Process;

/**
 * @author Raymond Schouten
 *
 * @since 1.0
 */
class PublicKeyFile extends CryptoFile
{
    const FORMAT_PEM = 'pem';
    const FORMAT_DER = 'der';

    /**
     * Validates that the file is actually a public key.
     *
     * @return bool
     */
    protected function validate()
    {
        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());

        $process = new Process("openssl x509 -in $in -inform $inForm -noout");
        $process->run();

        if (!$process->isSuccessful()) {
            return false;
        }

        $process = new Process("openssl rsa -in $in -inform $inForm -passin pass:anypass -check -noout");
        $process->run();

        $badDecrypt = false !== strpos($process->getErrorOutput(), ':bad decrypt:');

        if ($process->isSuccessful() || $badDecrypt) {
            return false;
        }

        return true;
    }

    /**
     * Sanitizes the public key, removing malicious data.
     *
     * @return \DarkWebDesign\PublicKeyCryptographyBundle\File\PublicKeyFile
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function sanitize()
    {
        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());

        $process = new Process("openssl x509 -in $in -inform $inForm -outform $inForm");
        $process->mustRun();

        @file_put_contents($this->getPathname(), $process->getOutput());
        @chmod($this->getPathname(), 0666 & ~umask());
        clearstatcache(true, $this->getPathname());

        return new self($this->getPathname());
    }

    /**
     * Gets the public key format (either ascii 'pem' or binary 'der').
     *
     * @return string
     */
    public function getFormat()
    {
        return $this->isBinary() ? static::FORMAT_DER : static::FORMAT_PEM;
    }

    /**
     * Gets the public key "subject" attribute.
     *
     * @return string
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getSubject()
    {
        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());

        $process = new Process("openssl x509 -in $in -inform $inForm -noout -subject");
        $process->mustRun();

        return trim(preg_replace('/^subject=/', '', $process->getOutput()));
    }

    /**
     * Gets the public key "issuer" attribute.
     *
     * @return string
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getIssuer()
    {
        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());

        $process = new Process("openssl x509 -in $in -inform $inForm -noout -issuer");
        $process->mustRun();

        return trim(preg_replace('/^issuer=/', '', $process->getOutput()));
    }

    /**
     * Gets the public key "notBefore" attribute.
     *
     * @return \DateTime
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getNotBefore()
    {
        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());

        $process = new Process("openssl x509 -in $in -inform $inForm -noout -startdate");
        $process->mustRun();

        return new \DateTime(trim(preg_replace('/^notBefore=/', '', $process->getOutput())));
    }

    /**
     * Gets the public key "notAfter" attribute.
     *
     * @return \DateTime
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getNotAfter()
    {
        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());

        $process = new Process("openssl x509 -in $in -inform $inForm -noout -enddate");
        $process->mustRun();

        return new \DateTime(trim(preg_replace('/^notAfter=/', '', $process->getOutput())));
    }

    /**
     * Converts the public key format to either ascii 'pem' or binary 'der'.
     *
     * @param string $format
     *
     * @return \DarkWebDesign\PublicKeyCryptographyBundle\File\PublicKeyFile
     *
     * @throws \DarkWebDesign\PublicKeyCryptographyBundle\Exception\FormatNotValidException
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function convertFormat($format)
    {
        $format = strtolower($format);

        if (!defined('DarkWebDesign\PublicKeyCryptographyBundle\File\PublicKeyFile::FORMAT_' . strtoupper($format))) {
            throw new FormatNotValidException($format);
        }

        if ($this->getFormat() === $format) {
            return $this;
        }

        $in = escapeshellarg($this->getPathname());
        $inForm = escapeshellarg($this->getFormat());
        $outForm = escapeshellarg($format);

        $process = new Process("openssl x509 -in $in -inform $inForm -outform $outForm");
        $process->mustRun();

        @file_put_contents($this->getPathname(), $process->getOutput());
        @chmod($this->getPathname(), 0666 & ~umask());
        clearstatcache(true, $this->getPathname());

        return new self($this->getPathname());
    }

    /**
     * Moves the file to a new location.
     *
     * @param string $directory
     * @param string|null $name
     *
     * @return \DarkWebDesign\PublicKeyCryptographyBundle\File\PublicKeyFile
     *
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function move($directory, $name = null)
    {
        $file = parent::move($directory, $name);

        return new self($file->getPathname());
    }
}
