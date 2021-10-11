<?php


namespace Opentalent\Websites\Exception;


use Exception;

/**
 * Class NoSuchWebsite
 * Raise this exception when website has no domain defined in db
 *
 * @package Opentalent\OtCore\Exception
 */
class InvalidWebsiteConfigurationException extends Exception {}
