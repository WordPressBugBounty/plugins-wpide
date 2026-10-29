<?php

namespace WPIDE\App\Services\Snippets;

/**
 * An error from the public snippet library on WPIDE Cloud (free and Pro), with the cloud's error code.
 */
class LibraryException extends \RuntimeException
{
    /** @var string */
    public $errorCode;

    public function __construct(string $message, int $status = 0, string $errorCode = '')
    {
        parent::__construct($message, $status);
        $this->errorCode = $errorCode;
    }
}
