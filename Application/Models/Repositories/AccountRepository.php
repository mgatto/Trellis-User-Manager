<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;

/**
 * AccountRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AccountRepository extends EntityRepository
{

    public function findOneById($id) {
        $query = $this->_em->createQuery('
            SELECT a FROM Entities\Account a
            WHERE a.id = :id
        ');
        $query->setParameter('id', $id);

        return $query->getSingleResult(Query::HYDRATE_OBJECT);
    }

    /**
     *
     *
     * @param string $token
     *
     * @return \Entities\Account
     */
    public function findOneByToken($token) {
        $query = $this->_em->createQuery('
            SELECT tk, u FROM Entities\Account u
            INNER JOIN u.tokens tk
            WHERE tk.token = :token
        ');
        $query->setParameter('token', $token);

        return $query->getSingleResult(Query::HYDRATE_OBJECT);
    }

    /**
     *
     *
     * @param string $token
     *
     * @return \Entities\Account
     */
    public function findOneByPasswordToken($token) {
        $query = $this->_em->createQuery('
            SELECT tk, u FROM Entities\Account u
            INNER JOIN u.tokens tk
            WHERE tk.token = :token
            AND tk.purpose = :purpose
        ');
        $query->setParameters(array(
            'token' => $token,
            'purpose' => 'password',
        ));

        return $query->getSingleResult(Query::HYDRATE_OBJECT);
    }

    /**
     *
     *
     * @param string $email
     *
     * @return \Entities\Account
     */
    public function findOneByEmail($email) {
        $query = $this->_em->createQuery('
            SELECT e, p, u FROM Entities\Account u
            INNER JOIN u.person p
            INNER JOIN p.emails e
            WHERE e.email= :email
        ');
        $query->setParameter('email', $email);

        try {
            $result = $query->getSingleResult(Query::HYDRATE_OBJECT);
        } catch (NoResultException $e) {
            return false;
        }

        return $result;
    }

    /**
     *
     *
     * @param string $username
     *
     * @return \Entities\Account
     */
    public function findOneByUsername($username) {
        $query = $this->_em->createQuery('
            SELECT u,p,e FROM Entities\Account u
            INNER JOIN u.person p
            LEFT JOIN p.emails e
            WHERE u.username = :username
        ');
        $query->setParameter('username', $username);

        try {
            $result = $query->getSingleResult(Query::HYDRATE_OBJECT);
        } catch (NoResultException $e) {
            return false;
        }

        return $result;
    }

    /**
     * Enter description here ...
     *
     * @param string $term
     *
     * @return mixed
     */
    public function findAllByPartialUsername($term) {
        $query = $this->_em
            ->createQuery('
                SELECT u.id, u.username, p.firstname, p.lastname, e.email, pst.name as position, i.name as institution
                FROM Entities\Account u
                    INNER JOIN u.person p
                        LEFT JOIN p.emails e
                        LEFT JOIN p.profile pf
                            LEFT JOIN pf.institution i
                            LEFT JOIN pf.position pst
                WHERE u.username LIKE :partial_username
                AND u.is_validated = :validated
                ORDER BY p.firstname, p.lastname
            ');
            /* LEFT JOIN pf.research_area ra
             * ra.name as research
             */

        $query->setParameters(array(
            'partial_username' => "%$term%",
            'validated' => 1,
        ));
        
        $query->setHint(\Doctrine\ORM\Query::HINT_INCLUDE_META_COLUMNS, TRUE);

        /* array is better and we really don't need objects right now */
        return $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }

    /**
     * Find by a full name fragment.
     *
     * @param string $term May be either a first or last name fragment
     *
     * @return Array
     */
    public function findAllByPartialFirstAndLastName($firstname, $lastname)
    {
        $query = $this->_em
            ->createQuery('
                SELECT u.id, u.username, p.firstname, p.lastname, e.email, pst.name as position, i.name as institution
                FROM Entities\Account u
                    INNER JOIN u.person p
                        LEFT JOIN p.emails e
                        LEFT JOIN p.profile pf
                            LEFT JOIN pf.institution i
                            LEFT JOIN pf.position pst
                WHERE p.firstname LIKE :firstname
                AND u.is_validated = :validated
                AND p.lastname LIKE :lastname
            ');
            //SELECT i.id as value, i.name as label
            //ORDER BY p.firstname, p.lastname - doing this separates matches, ugh

        $query->setParameters(array(
            'firstname' => "%$firstname%",
            'lastname' => "%$lastname%",
            'validated' => 1,
        ));

        /* array is better and we really don't need objects right now */
        return $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }

    /**
     * Find by single name fragment.
     *
     * @param string $term May be either a first or last name fragment
     *
     * @return \Entities\Account
     */
    public function findAllByPartialFirstOrLastName($term) {
        $query = $this->_em
            ->createQuery('
                SELECT u.id, u.username, p.firstname, p.lastname, e.email, pst.name as position, i.name as institution
                FROM Entities\Account u
                    INNER JOIN u.person p
                        LEFT JOIN p.emails e
                        LEFT JOIN p.profile pf
                            LEFT JOIN pf.institution i
                            LEFT JOIN pf.position pst
                WHERE p.firstname LIKE :partial_name
                AND u.is_validated = :validated
                OR p.lastname LIKE :partial_name
            ');
            //SELECT i.id as value, i.name as label
            //ORDER BY p.firstname, p.lastname - doing this separates matches, ugh

        $query->setParameters(array(
            'partial_name' => "%$term%",
            'validated' => 1,
        ));

        /* array is better and we really don't need objects right now */
        return $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $term
     *
     * @return return_type
     */
    public function findAllByPartialEmailAddress($term, array $limits) {
        $query = $this->_em
            ->createQuery('
                SELECT u.id, u.username, p.firstname, p.lastname, e.email, pst.name as position, i.name as institution
                FROM Entities\Account u
                    INNER JOIN u.person p
                        LEFT JOIN p.profile pf
                            LEFT JOIN pf.institution i
                            LEFT JOIN pf.position pst
                        LEFT JOIN p.emails e
                WHERE e.email LIKE :partial_email
                AND u.is_validated = :validated
                ORDER BY p.firstname, p.lastname
            ');

        /* inject result limits */
        $query->setFirstResult($limits['start']);
        $query->setMaxResults($limits['end']);

        $query->setParameters(array(
            'partial_email' => "%$term%",
            'validated' => 1,
        ));

        /* array is better and we really don't need objects right now */
        return $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    }
}
