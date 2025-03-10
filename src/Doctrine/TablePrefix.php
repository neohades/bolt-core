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
        echo 'Zrzut z bazy danych:<br >';
        var_dump($microservices);
        echo '<br />';
        $this->setTablePrefixes($tablePrefix, $managerRegistry);

        // var_dump('vendor/bolt/core/src/Doctrine/TablePrefix.php:_construct', $this->tablePrefixes);
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {

        $entityManager = $eventArgs->getEntityManager();
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $tablesInDB = $schemaManager->listTableNames();

        var_dump($tablesInDB);
       
        $tablePrefix = $this->getTablePrefix($entityManager);
        
        if($this->microservices){
            $domain = $_SERVER['SERVER_NAME'] ?? 'default';

            $sessionPrefix = $this->session->get('CURRENT_SERVICE');

            $checkIfAdminUrl = $this->checkIfAdminUrl($_SERVER['REQUEST_URI'] ?? '/');

            echo '<br>loadClass: SERVER_NAME:<br >';
            var_dump($_SERVER['SERVER_NAME'] ?? 'undefined');
            echo '<br />';
            echo 'loadClass: domain:<br >';
            var_dump($domain);
            echo '<br />';
            echo 'loadClass: sessionPrefix:<br >';
            var_dump($sessionPrefix);
            echo '<br />';
            echo 'loadClass: REQUEST_URI:<br >';
            var_dump($_SERVER['REQUEST_URI'] ?? 'undefined' );
            echo '<br />';
            echo 'loadClass: SCRIPT_NAME:<br >';
            var_dump($_SERVER['SCRIPT_NAME'] ?? 'undefined' );
            echo '<br />';
            echo 'loadClass: HTTP_HOST:<br >';
            var_dump($_SERVER['HTTP_HOST'] ?? 'undefined' );
            echo '<br />';
            echo 'loadClass: PHP_SELF:<br >';
            var_dump($_SERVER['PHP_SELF'] ?? 'undefined' );
            echo '<br />';
            echo 'loadClass: checkIfAdminUrl:<br >';
            var_dump($checkIfAdminUrl );
            echo '<br />';

            if( $sessionPrefix && $checkIfAdminUrl ){
                $domainBasedPrefix = $this->checkIfPrefixIsAllowed( $sessionPrefix );
                echo '<br>Warunek że sessionPrefix i jest adminem<br>'.$domainBasedPrefix.'<br><br>' ;
            }
            else{
                $domainBasedPrefix = $this->checkIfPrefixIsAllowed( $this->microservices[$domain] ?? 'bolt' );
                echo '<br>Warunek że sessionPrefix jest puste lub nie jest adminem<br>domena: '.$this->microservices[$domain].' --- prefixDomeny: '.$domainBasedPrefix.'<br><br>' ;
            }

            if($domainBasedPrefix){
                $tablePrefix = $domainBasedPrefix;
            }
        }
        echo '<br>tablePrefix: <br>'.$tablePrefix.'<br><br>' ;
        if ($tablePrefix) {
            $classMetadata = $eventArgs->getClassMetadata();

            if (! $classMetadata->isInheritanceTypeSingleTable()
                || $classMetadata->getName() === $classMetadata->rootEntityName) {
                $tableNameWithPrefix = $tablePrefix . $classMetadata->getTableName();
                
                echo '<br>tableNameWithPrefix: <br>'.$tableNameWithPrefix.'<br><br>' ;

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

                echo '<br>Końcowy warunek: <br>'.$tablePrefix . $classMetadata->getTableName().'<br><br>' ;
            }

            foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
                if ($mapping['type'] === ClassMetadataInfo::MANY_TO_MANY && $mapping['isOwningSide']) {
                    $mappedTableName = $mapping['joinTable']['name'];
                    $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $tablePrefix . $mappedTableName;
                }
            }
        }

        echo '<br><br><hr><br><br>';
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
