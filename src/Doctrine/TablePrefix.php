<?php

declare(strict_types=1);

namespace Bolt\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Doctrine\DBAL\Connection;


class TablePrefix
{
    use TablePrefixTrait;
    private $session;
    /** @var Connection */
    private $connection;

    private array $microservices;

    public function __construct(Connection $connection, $tablePrefix, ManagerRegistry $managerRegistry, SessionInterface $session, array $microservices = [])
    {
        $this->connection = $connection;
        $this->session = $session;
        try {
            $sql = "SELECT * FROM bolt_microservice WHERE active=1";
            $result = $this->connection->fetchAllAssociative($sql);

            foreach ($result as $row) {
                $microservices[$row['domain']] = $row['prefix'];
            }
        } catch (\Doctrine\DBAL\Exception $e) { 
        }
        $this->microservices = $microservices;
        $this->setTablePrefixes($tablePrefix, $managerRegistry);

        // var_dump('vendor/bolt/core/src/Doctrine/TablePrefix.php:_construct', $this->tablePrefixes);
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {

        $entityManager = $eventArgs->getEntityManager();
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $tablesInDB = $schemaManager->listTableNames();
       
        $tablePrefix = $this->getTablePrefix($entityManager);

        if($this->microservices){
            $domain = $_SERVER['SERVER_NAME'] ?? 'default';

            $sessionPrefix = $this->session->get('CURRENT_SERVICE');

            $checkIfAdminUrl = $this->checkIfAdminUrl($_SERVER['REQUEST_URI'] ?? '/');

            if( $sessionPrefix && $checkIfAdminUrl )
                $domainBasedPrefix = $this->checkIfPrefixIsAllowed( $sessionPrefix );
            else
                $domainBasedPrefix = $this->checkIfPrefixIsAllowed( $this->microservices[$domain] ?? 'bolt' );

            if($domainBasedPrefix){
                $tablePrefix = $domainBasedPrefix;
            }
        }

        if ($tablePrefix) {
            $classMetadata = $eventArgs->getClassMetadata();

            if (! $classMetadata->isInheritanceTypeSingleTable()
                || $classMetadata->getName() === $classMetadata->rootEntityName) {
                $tableNameWithPrefix = $tablePrefix . $classMetadata->getTableName();
                
                /*  sprawdzenie czy istnieje tabela z danym prefixem, jeśli nie, to bierze
                    domyślny prefix 
                */
                if(!in_array($tableNameWithPrefix, $tablesInDB))
                    $tablePrefix = 'bolt_';
                
                $classMetadata->setPrimaryTable(
                    [
                        'name' => $tablePrefix . $classMetadata->getTableName(),
                    ]
                );
            }

            foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
                if ($mapping['type'] === ClassMetadataInfo::MANY_TO_MANY && $mapping['isOwningSide']) {
                    $mappedTableName = $mapping['joinTable']['name'];
                    $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $tablePrefix . $mappedTableName;
                }
            }
        }
    }

    private function checkIfPrefixIsAllowed(string $checkedPrefix): string|bool {
// var_dump($this->tablePrefixes);
        if(!empty($this->tablePrefixes)){
            foreach($this->tablePrefixes as $prefix){
                if($prefix == $checkedPrefix.'_'){
                    return $prefix;
                }
            }
        }

        return false;
    }

    private function extractSubdomain(string $host): string
    {
        $parts = explode('.', $host);
        return $parts[0];
    }

    private function checkIfAdminUrl(string $url): bool
    {
        if (strpos($url, '/bolt/') !== false) 
            return true;
        else
            return false;
    }
}
