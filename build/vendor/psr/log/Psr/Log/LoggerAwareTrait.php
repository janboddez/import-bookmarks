<?php

namespace Import_Bookmarks\Psr\Log;

/**
 * Basic Implementation of LoggerAwareInterface.
 */
trait LoggerAwareTrait
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface|null
     */
    protected $logger;
    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(\Import_Bookmarks\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
