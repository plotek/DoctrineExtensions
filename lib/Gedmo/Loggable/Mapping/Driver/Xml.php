<?php

namespace Gedmo\Loggable\Mapping\Driver;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Gedmo\Mapping\Driver\Xml as BaseXml;
use Gedmo\Exception\InvalidMappingException;

/**
 * This is a xml mapping driver for Loggable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Loggable
 * extension.
 *
 * @author Boussekeyt Jules <jules.boussekeyt@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class Xml extends BaseXml
{
    /**
     * {@inheritDoc}
     */
    public function readExtendedMetadata($meta, array &$config)
    {
        /**
         * @var \SimpleXmlElement $xml
         */
        $xml = $this->_getMapping($meta->name);
        $xmlDoctrine = $xml;

        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);

        if ($xmlDoctrine->getName() == 'entity' || $xmlDoctrine->getName() == 'document' || $xmlDoctrine->getName() == 'mapped-superclass') {
            if (isset($xml->loggable)) {
                /**
                 * @var \SimpleXMLElement $data;
                 */
                $data = $xml->loggable;
                $config['loggable'] = true;
                if ($this->_isAttributeSet($data, 'log-entry-class')) {
                    $class = $this->_getAttribute($data, 'log-entry-class');
                    if (!$cl = $this->getRelatedClassName($meta, $class)) {
                        throw new InvalidMappingException("LogEntry class: {$class} does not exist.");
                    }
                    $config['logEntryClass'] = $cl;
                }
            }
        }

        if (isset($xmlDoctrine->field)) {
            $this->inspectElementForVersioned($xmlDoctrine->field, $config, $meta);
        }
        if (isset($xmlDoctrine->{'many-to-one'})) {
            $this->inspectElementForVersioned($xmlDoctrine->{'many-to-one'}, $config, $meta);
        }
        if (isset($xmlDoctrine->{'one-to-one'})) {
            $this->inspectElementForVersioned($xmlDoctrine->{'one-to-one'}, $config, $meta);
        }
        if (isset($xmlDoctrine->{'reference-one'})) {
            $this->inspectElementForVersioned($xmlDoctrine->{'reference-one'}, $config, $meta);
        }
        if (isset($xmlDoctrine->{'embedded'})) {
            $this->inspectEmbeddedForVersioned($xmlDoctrine->{'embedded'}, $config, $meta);
        }

        if (!$meta->isMappedSuperclass && $config) {
            if (is_array($meta->identifier) && count($meta->identifier) > 1) {
                throw new InvalidMappingException("Loggable does not support composite identifiers in class - {$meta->name}");
            }
            if ($this->isClassAnnotationInValid($meta, $config)) {
                throw new InvalidMappingException("Class must be annotated with Loggable annotation in order to track versioned fields in class - {$meta->name}");
            }
        }
    }

    /**
     * @param ClassMetadata $meta
     * @param array         $config
     *
     * @return bool
     */
    protected function isClassAnnotationInValid(ClassMetadata $meta, array &$config)
    {
        return isset($config['versioned']) && !isset($config['loggable']) && (!isset($meta->isEmbeddedClass) || !$meta->isEmbeddedClass);
    }

    /**
     * Searches mappings on element for versioned fields
     *
     * @param \SimpleXMLElement $element
     * @param array             $config
     * @param object            $meta
     */
    private function inspectElementForVersioned(\SimpleXMLElement $element, array &$config, $meta)
    {
        foreach ($element as $mapping) {
            $mappingDoctrine = $mapping;
            /**
             * @var \SimpleXmlElement $mapping
             */
            $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);

            $isAssoc = $this->_isAttributeSet($mappingDoctrine, 'field');
            $field = $this->_getAttribute($mappingDoctrine, $isAssoc ? 'field' : 'name');

            if (isset($mapping->versioned)) {
                if ($isAssoc && !$meta->associationMappings[$field]['isOwningSide']) {
                    throw new InvalidMappingException("Cannot version [{$field}] as it is not the owning side in object - {$meta->name}");
                }
                $config['versioned'][] = $field;
            }
        }
    }

    /**
     * Searches properties of embedded object for versioned fields
     *
     * @param string $field
     * @param array $config
     * @param \Doctrine\ORM\Mapping\ClassMetadata $meta
     */
    private function inspectEmbeddedForVersioned(\SimpleXMLElement $element, array &$config, \Doctrine\ORM\Mapping\ClassMetadata $meta)
    {
        foreach ($element as $mapping) {
            $mappingDoctrine = $mapping;
            /**
             * @var \SimpleXmlElement $mapping
             */
            $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);

            if (isset($mapping->versioned)) {
                if (!$this->_isAttributeSet($mappingDoctrine, 'class')) {
                    throw new InvalidMappingException("Embedded must set class attribute");
                }

                $class = $this->_getAttribute($mappingDoctrine, 'class');
                $field = $this->_getAttribute($mappingDoctrine, 'name');

                $xml = $this->_getMapping($class);

                if (isset($xml->field)) {
                    foreach ($xml->field as $embeddabledMapping) {
                        $embeddableMappingDoctrine = $embeddabledMapping;
                        /**
                         * @var \SimpleXmlElement $mapping
                         */
                        $embeddabledMapping = $embeddabledMapping->children(self::GEDMO_NAMESPACE_URI);

                        $embeddableField = $this->_getAttribute($embeddableMappingDoctrine, 'name');

                        if (isset($embeddabledMapping->versioned)) {
                            $config['versioned'][] = $field . '.' . $embeddableField;
                        }
                    }
                }
            }
        }
    }
}
