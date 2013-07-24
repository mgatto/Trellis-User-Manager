<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * ServiceRequestRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ApiClientRepository extends EntityRepository
{

    /**
     * Get 0 or more of the user's existing api clients as an array
     *
     * @param int $service_id
     * @param int $account_id
     *
     * @return mixed
     */
    public function findAllUserClients($account_id) {
        $account_api_query = $this->_em
            ->createQuery("
                SELECT apc, a, ap, m, ac FROM Entities\ApiClient apc
                INNER JOIN apc.account a
                INNER JOIN apc.api ap
                LEFT JOIN ap.maintainer m
                LEFT JOIN ap.actions ac
                WHERE
                    a.id = :account_id
                AND apc.approval IN ('approved')
            ");

        $account_api_query->setParameters(array(
            'account_id'=> $account_id,
        ));

        return $account_api_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }


    /**
     * Find all a user's service requests
     *
     * @param int $user_id
     *
     * @return mixed
     */
    public function findAllPendingUserClients($account_id) {
        $pending_clients_query = $this->_em
            ->createQuery("
                SELECT apc, ap, a, m, ac FROM Entities\ApiClient apc
                INNER JOIN apc.account a
                INNER JOIN apc.api ap
                LEFT JOIN ap.maintainer m
                LEFT JOIN ap.actions ac
                WHERE
                    a.id = :account_id
                AND
                    apc.approval != 'approved'
                ORDER BY ap.name
            ");

        $pending_clients_query->setParameter('account_id', $account_id);

        return $pending_clients_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }


    /**
     * Find 1 API Client
     *
     * NoUniqueException is thrown if more thn one.
     *
     * @param int $service_id
     * @param int $account_id
     *
     * @return ServiceRequest
     */
    public function findOneByApiAndAccount($api_id, $account_id) {
        $client_query = $this->_em
            ->createQuery("
                SELECT apc, a, ap, m, ac FROM Entities\ApiClient apc
                INNER JOIN apc.account a
                INNER JOIN apc.api ap
                LEFT JOIN ap.maintainer m
                LEFT JOIN ap.actions ac
                WHERE
                    a.id = :account_id
                AND
                    ap.id = :api_id
            ");

        $client_query->setParameters(array(
            'api_id' => $api_id,
            'account_id'=> $account_id,
        ));

        return $client_query->getSingleResult(\Doctrine\ORM\Query::HYDRATE_OBJECT);
    }

 /**
     * Find 1 service request
     *
     * NoUniqueException is thrown if more thn one.
     *
     * @param int $service_id
     * @param int $account_id
     *
     * @return ServiceRequest
     */
    public function findAllByApiAndAccount($api_id, $account_id) {
        $client_query = $this->_em
            ->createQuery("
                SELECT apc, a, ap, m, ac FROM Entities\ApiClient apc
                INNER JOIN apc.account a
                INNER JOIN apc.api ap
                LEFT JOIN ap.maintainer m
                LEFT JOIN ap.actions ac
                WHERE
                    a.id = :account_id
                AND
                    ap.id = :api_id
            ");

        $client_query->setParameters(array(
            'api_id' => $api_id,
            'account_id'=> $account_id,
        ));

        return $client_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }

    /**
     * Find 1 API Client
     *
     * NoUniqueException is thrown if more thn one.
     *
     * @param int $client_id
     * @param int $account_id
     *
     * @return ServiceRequest
     */
    public function findOneByClientIdAndUsername($client_id, $username)
    {
        $client_query = $this->_em
            ->createQuery("
                SELECT apc, a, ap, m, ac FROM Entities\ApiClient apc
                INNER JOIN apc.account a
                INNER JOIN apc.api ap
                LEFT JOIN ap.maintainer m
                LEFT JOIN ap.actions ac
                WHERE
                    a.username = :username
                AND
                    apc.id = :client_id
            ");

        $client_query->setParameters(array(
            'client_id' => $client_id,
            'username'=> $username,
        ));

        return $client_query->getSingleResult(\Doctrine\ORM\Query::HYDRATE_OBJECT);
    }

    /**
     * Find 1 API Client
     *
     * NoUniqueException is thrown if more thn one.
     *
     * @param int $client_id
     * @param int $account_id
     *
     * @return ServiceRequest
     */
    public function findOneByIdAndAccount($client_id, $account_id) {
        $client_query = $this->_em
            ->createQuery("
                SELECT apc, a, ap, m, ac, p FROM Entities\ApiClient apc
                INNER JOIN apc.account a
                INNER JOIN a.person p
                INNER JOIN apc.api ap
                LEFT JOIN ap.maintainer m
                LEFT JOIN ap.actions ac
                WHERE
                    a.id = :account_id
                AND
                    apc.id = :client_id
            ");

        $client_query->setParameters(array(
            'client_id' => $client_id,
            'account_id'=> $account_id,
        ));

        return $client_query->getSingleResult(\Doctrine\ORM\Query::HYDRATE_OBJECT);
    }
}