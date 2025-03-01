<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\SessionMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

class SessionService {
	/** @var SessionMapper */
	protected $sessionMapper;
	/** @var IDBConnection */
	protected $connection;
	/** @var ISecureRandom */
	protected $secureRandom;

	public function __construct(SessionMapper $sessionMapper,
								IDBConnection $connection,
								ISecureRandom $secureRandom) {
		$this->sessionMapper = $sessionMapper;
		$this->connection = $connection;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * Update last ping for multiple sessions
	 *
	 * Since this function is called by the HPB with potentially hundreds of
	 * sessions, we do not use the SessionMapper to get the entities first, as
	 * that would just not scale good enough.
	 *
	 * @param string[] $sessionIds
	 * @param int $lastPing
	 */
	public function updateMultipleLastPings(array $sessionIds, int $lastPing): void {
		$query = $this->connection->getQueryBuilder();
		$query->update('talk_sessions')
			->set('last_ping', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT))
			->where($query->expr()->in('session_id', $query->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)));

		$query->execute();
	}

	public function updateLastPing(Session $session, int $lastPing): void {
		$session->setLastPing($lastPing);
		$this->sessionMapper->update($session);
	}

	/**
	 * @param int[] $ids
	 */
	public function deleteSessionsById(array $ids): void {
		$this->sessionMapper->deleteByIds($ids);
	}

	public function createSessionForAttendee(Attendee $attendee): Session {
		// Currently a participant can only join once
		$this->sessionMapper->deleteByAttendeeId($attendee->getId());

		$session = new Session();
		$session->setAttendeeId($attendee->getId());

		while (true) {
			$sessionId = $this->secureRandom->generate(255);
			$session->setSessionId($sessionId);
			try {
				$this->sessionMapper->insert($session);
				break;
			} catch (UniqueConstraintViolationException $e) {
				// 255 chars are not unique? Try again...
			}
		}

		return $session;
	}
}
