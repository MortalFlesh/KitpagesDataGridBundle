<?php

namespace Kitpages\DataGridBundle\Hydrators;

use Doctrine\DBAL\Types\Type;

class DataGridHydrator extends \Doctrine\ORM\Internal\Hydration\AbstractHydrator
{

    /**
     * {@inheritdoc}
     */
    protected function hydrateAllData()
    {
        $result = array();
        $cache  = array();

        while ($data = $this->_stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->hydrateRowData($data, $cache, $result);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function hydrateRowData(array $data, array &$cache, array &$result)
    {
        $result[] = $this->gatherScalarRowData($data, $cache);
    }

    protected function gatherScalarRowData(&$data, &$cache)
    {
        $rowData = array();

        foreach ($data as $key => $value) {
            // Parse each column name only once. Cache the results.
            if ( ! isset($cache[$key])) {
                switch (true) {
                    // NOTE: During scalar hydration, most of the times it's a scalar mapping, keep it first!!!
                    case (isset($this->_rsm->scalarMappings[$key])):
                        $cache[$key]['fieldName'] = $this->_rsm->scalarMappings[$key];
                        $cache[$key]['isScalar']  = true;
                        break;

                    case (isset($this->_rsm->fieldMappings[$key])):
                        $fieldName     = $this->_rsm->fieldMappings[$key];
                        $classMetadata = $this->_em->getClassMetadata($this->_rsm->declaringClasses[$key]);

                        $cache[$key]['fieldName'] = $fieldName;
                        $cache[$key]['type']      = Type::getType($classMetadata->fieldMappings[$fieldName]['type']);
                        $cache[$key]['dqlAlias']  = $this->_rsm->columnOwnerMap[$key];
                        break;

                    case (isset($this->_rsm->metaMappings[$key])):
                        // Meta column (has meaning in relational schema only, i.e. foreign keys or discriminator columns).
                        $cache[$key]['isMetaColumn'] = true;
                        $cache[$key]['fieldName']    = $this->_rsm->metaMappings[$key];
                        $cache[$key]['dqlAlias']     = $this->_rsm->columnOwnerMap[$key];
                        break;

                    default:
                        // this column is a left over, maybe from a LIMIT query hack for example in Oracle or DB2
                        // maybe from an additional column that has not been defined in a NativeQuery ResultSetMapping.
                        continue 2;
                }
            }

            $fieldName = $cache[$key]['fieldName'];

            switch (true) {
                case (isset($cache[$key]['isScalar'])):
                    $rowData[$fieldName] = $value;
                    break;

                case (isset($cache[$key]['isMetaColumn'])):
                    $rowData[$cache[$key]['dqlAlias'] . '.' . $fieldName] = $value;
                    break;

                default:
                    $value = $cache[$key]['type']->convertToPHPValue($value, $this->_platform);

                    $rowData[$cache[$key]['dqlAlias'] . '.' . $fieldName] = $value;
            }
        }

        return $rowData;
    }
}
