<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
 *
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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

namespace OCA\Talk\Command\Room;

use Exception;
use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\IConfig;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {
	use TRoomCommand;

	/** @var IUserManager */
	public $userManager;

	/** @var Manager */
	public $manager;

	public function __construct(IUserManager $userManager, Manager $manager) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->manager = $manager;
	}

	protected function configure(): void {
		$this
			->setName('talk:room:add')
			->setDescription('Adds users to a room')
			->addArgument(
				'token',
				InputArgument::REQUIRED,
				'Token of the room to add users to'
			)->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Invites the given users to the room'
			)->addOption(
				'group',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Invites all members of the given groups to the room'
			)->addOption(
				'circle',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Invites all members of the given circles to the room'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): ?int {
		$token = $input->getArgument('token');
		$users = $input->getOption('user');
		$groups = $input->getOption('group');
		$circles = $input->getOption('circle');

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$output->writeln('<error>Room not found.</error>');
			return 1;
		}

		if (!in_array($room->getType(), [Room::GROUP_CALL, Room::PUBLIC_CALL], true)) {
			$output->writeln('<error>Room is no group conversation.</error>');
			return 1;
		}

		try {
			$this->addRoomParticipants($room, $users);
			$this->addRoomParticipantsByGroup($room, $groups);
			$this->addRoomParticipantsByCircle($room, $circles);
		} catch (Exception $e) {
			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			return 1;
		}

		$output->writeln('<info>Users successfully added to room.</info>');
		return 0;
	}
}
