<?php

namespace DTApi\Http\Controllers;

use Cassandra\Exception\ValidationException;
use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Exception;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->input('user_id');
            $userType = $request->__authenticatedUser->user_type;

            if ($userId) {
                $response = $this->repository->getUsersJobs($userId);
            } elseif ($userType == envv('ADMIN_ROLE_ID') || $userType == env('SUPERADMIN_ROLE_ID')) {
                $response = $this->repository->getAll($request);
            }

            return response($response);
        } catch (Exception $e) {
           return response(['error' => $e->getMessage()]);
        }

    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        try {
            $job = $this->repository->with('translatorJobRel.user')->find($id);
            if ($job === null) {
                return response('Job not found', 404);
            }
        } catch (Exception $e) {
            return response(['error' => $e->getMessage()]);
        }

        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        try {

            // I would definitely add validation rules here.
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'string',
            ]);

            // Check if validation fails
            if ($validatedData->fails()) {
                return response('validation error', 422);
            }

            $data = $request->all();

            $response = $this->repository->store($request->__authenticatedUser, $data);

        } catch (Exception $e) {
            return response(['error' => $e->getMessage()]);
        }
        return response($response, 201);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        try {

            // I would definitely add validation rules here.
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'string',
            ]);

            // Check if validation fails
            if ($data->fails()) {
                return response('validation error', 422);
            }

            $cuser = $request->__authenticatedUser;
            $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        } catch (Exception $e) {
            return response(['error' => $e->getMessage()]);
        }

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {

        try {
            $data = $request->validate([
                'email' => 'required|string|email|max:255',
            ]);

            $response = $this->repository->storeJobEmail($data);

        } catch (ValidationException $e) {
            // I would use validation exception here we can use this too instead of basic if condition
            return response($e->validator->errors(), 422);
        } catch (Exception $e) {
            return response(['error' => $e->getMessage()]);
        }

        return response($response, 200);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if ($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {

        try {


            $data = $request->all();

            //case 1 where we can use ternary operators instead of if else easy to use and less syntax (my fav way)
            $jobid = $data['jobid'] ?? null;
            $distance = $data['distance'] ?? null;
            $time = $data['time'] ?? null;
            $session = $data['session_time'] ?? null;
            $flagged = $data['flagged'] === 'true' ? 'yes' : 'no';
            $manually_handled = $data['manually_handled'] === 'true' ? 'yes' : 'no';
            //this can be used too here both does the same thing its just way of coding style.
            switch ($data['manually_handled']) {
                case 'true':
                    $manually_handled = 'yes';
                    break;
                default:
                    $manually_handled = 'no';
            }
            $by_admin = $data['by_admin'] === 'true' ? 'yes' : 'no';
            $admincomment = $data['admincomment'] ?? null;


            if ($distance !== null || $time !== null) {
                Distance::where('job_id', $jobid)->update([
                    'distance' => $distance,
                    'time' => $time,
                ]);
            }

            if ($session !== null || $admincomment !== null || $flagged !== 'no' || $manually_handled !== 'no' || $by_admin !== 'no') {
                Job::where('id', $jobid)->update([
                    'admin_comments' => $admincomment,
                    'flagged' => $flagged,
                    'session_time' => $session,
                    'manually_handled' => $manually_handled,
                    'by_admin' => $by_admin,
                ]);
            }

        }
        catch (Exception $e){
            return response(['error' => $e->getMessage()]);
        }
        return response('Record updated!',201);
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
