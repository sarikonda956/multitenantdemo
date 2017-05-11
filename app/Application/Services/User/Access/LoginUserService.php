<?php
namespace App\Application\Services\User\Access;

use App\Domain\User\Authentifier;
use App\Application\Services\ApplicationService;
use App\Domain\User\Entities\User;
use App\Application\Services\User\Access\SignUpUserRequest;
use App\Domain\User\Exceptions\UserAlreadyExistsException;

/**
 * Class LogInUserService
 *
 * @package App\Application\Services\User\Access
 * @author thanos theodorakopoulos galousis@gmail.com
 */
class LogInUserService implements ApplicationService
{

	#region properties
	/** @var Authentifier  */
	private $authenticationService;
	#endregion

	#region COnstructor
	public function __construct(Authentifier $authenticationService)
	{
		$this->authenticationService = $authenticationService;
	}
	#endregion

	#region Methods
	/**
	 * @param SignUpUserRequest $request
	 * @return User
	 * @throws UserAlreadyExistsException
	 */
	public function execute($request = null)
	{
		$email 		= $request->email();
		$password 	= $request->password();

		return $this->authenticationService->authenticate($email, $password);
	}
	#endregion
}
