<?php
namespace App\Interfaces\Api\Http\Controllers;

//use App\Application\Services\UserService;
use App\Interfaces\Api\Http\Response\JsonResponseDefault;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Application\Services\User\Access\LogInUserService;
use App\Application\Services\User\Access\LogOutUserService;
use App\Application\Services\User\Access\SignUpUserRequest;
use App\Application\Services\User\Access\SignUpUserService;
use App\Application\Services\User\Create\CreateUserService;
use App\Application\Services\User\Access\GetUserByService;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Domain\User\Entities\User;
use Config;

/**
 * Class UserController
 *
 * @package App\Interfaces\Web\Http\Controllers\User
 * @author thanos theodorakopoulos galousis@gmail.com
 */
class UserController extends ApiController
{

	#region Properties
	/** @var SignUpUserRequest */
	public $signUpUserRequest;

	/** @var LogInUserService  */
	public $logInUserService;

	/** @var LogOuUserService  */
	public $logOutUserService;

	/** @var SignUpUserService  */
	public $signUpUserService;

	/** @var CreateUserService  */
	public $createUserService;

	/** @var GetUserByService  */
	public $getUserBy;
	#endregion

	#region Constructor
	/**
	 * UserController constructor.
	 *
	 * @param SignUpUserRequest $signUpUserRequest
	 * @param LogInUserService $logInUserService
	 * @param LogOutUserService $logOutUserService
	 * @param SignUpUserService $signUpUserService
	 * @param CreateUserService $createUserService
	 * @param GetUserByService $getUserBy
	 */
	public function __construct(
		SignUpUserRequest $signUpUserRequest,
		LogInUserService $logInUserService, LogOutUserService $logOutUserService,
		SignUpUserService $signUpUserService, CreateUserService $createUserService,
		GetUserByService $getUserBy
	)
	{
		$this->signUpUserRequest 	= $signUpUserRequest;
		$this->logInUserService 	= $logInUserService;
		$this->logOutUserService	= $logOutUserService;
		$this->signUpUserService 	= $signUpUserService;
		$this->createUserService	= $createUserService;
		$this->getUserBy			= $getUserBy;
	}
	#endregion

	#region Methods
	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function login(Request $request)
	{
		return $this->logInUserService->execute($request);
	}

	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function signup(Request $request)
	{
		/** @var JsonResponse $result */
		$result = $this->signUpUserService->execute($request);

		//TODO
//		$hasToReleaseToken 	= Config::get('auth.signup_token_release');
//		if($result->content())
//		if($hasToReleaseToken) {
//			return $this->login($request);
//		}

		return $result;
	}

	/**
	 * @param Request $request
	 */
	public function recovery(Request $request)
	{
		//TODO
	}

	/**
	 * @param Request $request
	 */
	public function reset(Request $request)
	{
		//TODO
	}

	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function create(Request $request)
	{
		return $this->createUserService->execute($request);
	}

	/**
	 * @param Request $request
	 * @return \App\Domain\User\Entities\User
	 */
	public function getByPage(Request $request)
	{
		return $this->getUserBy->execute($request);
	}

	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function index(Request $request)
	{

		/** @var LengthAwarePaginator $allUsers */
		$allUsers = $this->getUserBy->execute($request);


		$allUsers = $allUsers->toArray();

		$data = $allUsers['data'];

		foreach ($allUsers['data'] as $key => $value)
		{
			/** @var User $user */
			$user = $value;
			$allUsers['data'][$key] = array('id'=>$user->getId(), 'name'=>$user->getName(), 'email'=>$user->getEmail());
		}

		return $allUsers;

//		$r = $this->getUserBy->execute($request);
//
//		return $r;
	}

	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function getByFilter(Request $request)
	{
		$criteria = $request->only(['filter']);
		return $this->getUserBy->execute($criteria['filter']);
	}

	public function me()
	{
		$result = JWTAuth::parseToken()->authenticate();
		return $result;
	}
	#endregion

}