<?php

namespace Cognesy\Utils\Xml;

use RuntimeException;

/**
 * Validates XML against the XML specification.
 */
class XmlValidator
{
    /**
     * Validates the given XML string.
     *
     * @param string $xml The XML string to validate.
     * @throws RuntimeException If the XML is invalid.
     */
    public function validate(string $xml): void
    {
        $previous = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $result = $dom->loadXML($xml, LIBXML_NONET);

        if (!$result) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            throw new RuntimeException(
                'Invalid XML: ' . $this->formatLibXmlError($errors[0])
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    /**
     * Formats error object as a string.
     *
     * @param \LibXMLError $error The error to format.
     * @return string The formatted error message.
     */
    private function formatLibXmlError(\LibXMLError $error): string
    {
        $message = $error->message;
        if ($error->line !== 0) {
            $message .= " on line {$error->line}";
        }
        if ($error->column !== 0) {
            $message .= " column {$error->column}";
        }
        return trim($message);
    }
}
