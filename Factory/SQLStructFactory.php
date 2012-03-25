<?php

/**
 * This file is part of the RollerworksRecordFilterBundle.
 *
 * (c) Rollerscapes
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link    http://projects.rollerscapes.net/RollerFramework
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

namespace Rollerworks\RecordFilterBundle\Factory;

use Rollerworks\RecordFilterBundle\Formatter\DomainAwareFormatterInterface;
use Rollerworks\RecordFilterBundle\Formatter\FormatterInterface;
use Rollerworks\RecordFilterBundle\Record\SQL\WhereStruct;
use Doctrine\DBAL\Connection;

/**
 * This factory is used to create 'Domain specific' RecordFilter SQLStruct Classes at runtime.
 *
 * The information is read from the Annotations of the Class.
 *
 * IMPORTANT: The Namespace must be the same as the one used with FormatterFactory
 *
 * The intent of this approach is to provide an interface that is Domain specific.
 * So its safe to assume that the 'correct' filtering configuration is used.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @api
 */
class SQLStructFactory extends AbstractSQLFactory
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $DBAL;

    protected $annotation = '\\Rollerworks\\RecordFilterBundle\\Annotation\\SQLStruct';

    /**
     * Set the default DB Driver.
     *
     * @param Connection $DBConnection
     *
     * @api
     */
    public function setDBConnection(Connection $DBConnection)
    {
        $this->DBAL = $DBConnection;
    }

    /**
     * Gets a reference instance for the filter getSQLStruct.
     * The correct annotations is searched by the information of $formatter
     *
     * $formatter must be an reference to the 'domain specific' ValidationFormatter annotations.
     *
     * @param DomainAwareFormatterInterface   $formatter
     * @param Connection                      $DBConnection
     * @return WhereStruct
     *
     * @api
     */
    public function getSQLStruct(DomainAwareFormatterInterface $formatter, Connection $DBConnection = null)
    {
        if (empty($DBConnection) && empty($this->DBAL)) {
            throw new \RuntimeException('No DB Driver configured/given.');
        }
        elseif (empty($DBConnection)) {
            $DBConnection = $this->DBAL;
        }

        $class = get_class($formatter);

        $entityNs = $this->getFormatterNS($class);
        $FQN      = $this->namespace . $entityNs . '\SQLStruct';

        if (!class_exists($FQN, false)) {
            $fileName = $this->classesDir . DIRECTORY_SEPARATOR . $entityNs . DIRECTORY_SEPARATOR . 'SQLStruct.php';

            if ($this->autoGenerate) {
                $this->generateClass($formatter->getBaseClassName(), $entityNs, $this->annotationReader->getClassAnnotations(new \ReflectionClass($formatter->getBaseClassName())), $this->classesDir);
            }

            if (!file_exists($fileName)) {
                throw new \RuntimeException('Missing file: ' . $fileName);
            }

            require $fileName;
        }

        return new $FQN($formatter, $DBConnection);
    }

    /**
     * Generates a annotations file.
     *
     * @param string $class
     * @param string $entityCompactNS
     * @param object $annotations
     * @param string $toDir
     */
    protected function generateClass($class, $entityCompactNS, $annotations, $toDir)
    {
        $whereBuilder = $this->generateQuery($annotations);
        $placeholders = array('<namespace>', '<OrigClass>', '<whereBuilder>');

        $replacements = array($this->namespace . $entityCompactNS, $class, $whereBuilder);

        $file = str_replace($placeholders, $replacements, self::$_ClassTemplate);
        $dir  = $toDir . DIRECTORY_SEPARATOR . $entityCompactNS;

        if (!file_exists($dir) && !mkdir($dir, 0777, true)) {
            throw new \RuntimeException('Was unable to create the Entity sub-dir for the RecordFilter::Record::SQL::SQLStruct.');
        }

        file_put_contents($dir . DIRECTORY_SEPARATOR . 'SQLStruct.php', $file, LOCK_EX);
    }

    /** Class code template */
    protected static $_ClassTemplate =
'<?php

namespace <namespace>;

use Rollerworks\RecordFilterBundle\Record\SQL\WhereStruct;
use Rollerworks\RecordFilterBundle\Record\SQL\DomainAwareSQLRecordInterface;
use Rollerworks\RecordFilterBundle\Formatter\DomainAwareFormatterInterface;

/**
 * THIS CLASS WAS GENERATED BY Rollerworks::RecordFilterBundle. DO NOT EDIT THIS FILE.
 */
class SQLStruct extends WhereStruct implements DomainAwareSQLRecordInterface
{
    public function __construct(DomainAwareFormatterInterface $formatter, $DBAL)
    {
        parent::__construct($formatter, $DBAL);
    }

    public function getBaseClassName()
    {
        return \'<OrigClass>\';
    }

    <whereBuilder>
}';
}
