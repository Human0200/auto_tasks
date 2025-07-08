<?php

require_once __DIR__.'/../bitrix/settings.php';

class Auth
{
    /**
     * @param  string  $url  The resource from which auth token is retrived. Works only with XML yet.
     * @return string|bool Auth token or FALSE on failure.
     */
    public static function auth(): string|bool
    {
        $url = ISP_URL.'?out=xml&func=auth&username='.ISP_LOGIN.'&password='.ISP_PASSWORD;

        $xml = XMLReader::open($url);
        if (! $xml) {
            return false;
        }
        $data = self::parseAuthFromXML($xml);
        if (! $xml->close()) {
            return false;
        }

        return $data;
    }

    /**
     * @param  XMLReader  $xml  A reader from which data is parsed. The caller should close it themselves.
     * @return string Auth token.
     */
    private static function parseAuthFromXML(XMLReader $xml): string
    {
        $auth = '';
        while ($xml->read()) {
            if ($xml->nodeType == XMLReader::ELEMENT && $xml->name === 'auth') {
                $auth = $xml->readInnerXml();
                break; // We don't need to read anymore, because we've already retrived the auth id
            }
        }

        return $auth;
    }
}
