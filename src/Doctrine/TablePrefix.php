<?php

declare(strict_types=1);

namespace Bolt\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TablePrefix
{
    use TablePrefixTrait;
    private $session;

    public function __construct($tablePrefix, ManagerRegistry $managerRegistry, SessionInterface $session)
    {
        $this->session = $session;
        $this->setTablePrefixes($tablePrefix, $managerRegistry);

        // var_dump('vendor/bolt/core/src/Doctrine/TablePrefix.php:_construct', $this->tablePrefixes);
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {

        $entityManager = $eventArgs->getEntityManager();
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $tablesInDB = $schemaManager->listTableNames();
       

        $tablePrefix = $this->getTablePrefix($entityManager);
        
        /* INFO
            uwaga! zmienna może pokazać coś innego niż nazwę hosta
        */

        $subdomain = $this->extractSubdomain($_SERVER['SERVER_NAME']);
        $sessionSubdomain = $this->session->get('CURRENT_SERVICE');;
        $checkIfAdminUrl = $this->checkIfAdminUrl($_SERVER['REQUEST_URI']);

        if( $sessionSubdomain && $checkIfAdminUrl )
            $subdomainBasedPrefix = $this->checkIfSubdomainIsAllowed( $sessionSubdomain );
        else
            $subdomainBasedPrefix = $this->checkIfSubdomainIsAllowed( $subdomain );

        if($subdomainBasedPrefix){
            $tablePrefix = $subdomainBasedPrefix;
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

    private function checkIfSubdomainIsAllowed(string $subdomain): string|bool {

        if(!empty($this->tablePrefixes)){
            foreach($this->tablePrefixes as $prefix){
                if($prefix == $subdomain.'_'){
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
