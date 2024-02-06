<?php

namespace App\Controllers\V1\App;

use App\Controllers\BaseController;
use App\Models\V1\App\AppActivityLogModel;
use App\Models\V1\App\PutawayItemModel;
use App\Models\V1\App\PutawayModel;
use App\Models\V1\App\ReceivingItemModel;
use App\Models\V1\App\ReceivingSessionModel;
use App\Models\V1\ApprovalCodeModel;
use App\Models\V1\LocationModel;
use App\Models\V1\ParkingBayModel;
use App\Models\V1\ProductModel;
use App\Models\V1\QueueModel;
use App\Models\V1\SerialNumbersModel;
use App\Models\V1\SerialNumbersStatusModel;
use App\Models\V1\UpdateStatusModel;
use App\Models\V1\UserModel;
use App\Models\V1\UserSessionsModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Ulid\Ulid;

class Receiving extends BaseController
{
    use ResponseTrait;

    private $AppActivityLogModel;

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return ResponseInterface
     */
    public function __construct()
    {
        $this->QueueModel               = new QueueModel();
        $this->ParkingBayModel          = new ParkingBayModel();
        $this->UserSessionsModel        = new UserSessionsModel();
        $this->ReceivingSessionModel    = new ReceivingSessionModel();
        $this->ProductModel             = new ProductModel();
        $this->UserModel                = new UserModel();
        $this->ReceivingItemModel       = new ReceivingItemModel();
        $this->PutawayModel             = new PutawayModel();
        $this->PutawayItemModel         = new PutawayItemModel();
        $this->ApprovalCodeModel        = new ApprovalCodeModel();
        $this->UpdateStatusModel        = new UpdateStatusModel();
        $this->LocationModel            = new LocationModel();
        $this->SerialNumbersModel       = new SerialNumbersModel();
        $this->SerialNumbersStatusModel = new SerialNumbersStatusModel();
        $this->AppActivityLogModel      = new AppActivityLogModel();
    }

    public function queueList()
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'limit' => $this->request->getGet('limit'),
            'page'  => $this->request->getGet('page'),
        ];
        if (! $validation->run($data, 'datatable')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();

        $limit = $validatedData['limit'];
        $page  = $validatedData['page'];

        $page      = (int) $page;
        $offset    = ($page - 1) * $limit;
        $data      = $this->QueueModel->getQueueList($offset, $limit);
        $total     = $this->QueueModel->getTotalQueueList();
        $totalPage = ceil($total / $limit);

        $list = [
            'lists'      => $data,
            'total_page' => (string) $totalPage,
            'page'       => (string) $page,
            'limit'      => $limit,
        ];
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User view queue list',
            moduleId: RECEIVING_MODULE,
            action: 1,
            idAffected: 0,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Basic.SuccessDataretrived'), $list);

        return $this->respond($response, $response['status']);
    }

    public function parkingBay($location, $queue_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $status     = 200;
        $validation = \Config\Services::validation();
        $data       = [
            'queue_uuid'  => $queue_uuid,
            'parking_bay' => $this->request->getPost('parking_bay'),
        ];

        if (! $validation->run($data, 'parkingbay')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $parkingBay    = $validatedData['parking_bay'];

        $checkParkingBay = $this->ParkingBayModel->checkParkingBay($parkingBay, RECEIVING);
        $ParkingBaysUuid = $this->ParkingBayModel->getParkingBaysUuid($parkingBay, RECEIVING);
        $checkQueue      = $this->QueueModel->getQueueInfo($queue_uuid);
        $checkLocation   = $this->LocationModel->getLocation($location);

        if (! $checkParkingBay) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkLocation) {
            $response = responseFormater(400, lang('Receiving.LocationNotFound'));

            return $this->respond($response, $response['status']);
        }

        $ulid = Ulid::generate(true);
        $data = [
            'session_uuid'   => $ulid,
            'users_id'       => $userId,
            'sub_modules_id' => RECEIVING_MODULE,
            'status'         => USER_ACTIVE,
            'created_at'     => getDateTime(),
            'location_in'    => $checkParkingBay->id,
            'locations_id'   => $checkLocation->id,
        ];
        $checkUserSession = $this->UserSessionsModel->checkUserSession('', RECEIVING_MODULE, $userId);
        if ($checkUserSession) {
            $whereCondition = [
                'id' => $checkUserSession->id,
            ];
            $setColumn = [
                'status' => INACTIVE,
            ];
            $updateUserSession = $this->UserSessionsModel->update($whereCondition, $setColumn);
        }

        $userSessionsid = $this->UserSessionsModel->insert($data);
        $insertedId     = $this->UserSessionsModel->getInsertID();
        if (! $userSessionsid) {
            $response = responseFormater(500, lang('Receiving.FailedScanParkingBay'));

            return $this->respond($response, $response['status']);
        }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User scan parking bay',
            moduleId: RECEIVING_MODULE,
            action: 0,
            idAffected: $insertedId,
            recordAffected: []
        );

        if ($this->UpdateStatusModel->UpdateStatus($checkQueue->id, RECEIVING_ONGOING, ACTIVE, $userId, '')) {
            $item = [
                'session_uuid'      => (string) $ulid,
                'parking_bays_uuid' => (string) $ParkingBaysUuid->parking_bays_uuid,
            ];
            $response = responseFormater($status, lang('Receiving.SuccessScanParkingBay'), $item);

            return $this->respond($response, $response['status']);
        }
        $response = responseFormater(500, lang('Receiving.FailedScanParkingBay'));

        return $this->respond($response, $response['status']);
    }

    public function queueItem($queue_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'queue_uuid'   => $queue_uuid,
            'session_uuid' => $this->request->getGet('session_uuid'),
            'limit'        => $this->request->getGet('limit'),
            'page'         => $this->request->getGet('page'),
        ];

        if (! $validation->run($data, 'queueItem')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $session_uuid  = $validatedData['session_uuid'];
        $limit         = $validatedData['limit'];
        $page          = $validatedData['page'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);

        $checkQueue = $this->QueueModel->getQueueInfo($queue_uuid);

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }

        $page   = (int) $page;
        $offset = ($page - 1) * $limit;

        $data      = $this->QueueModel->getQueueItemList($limit, $offset, $checkQueue->id, $checkUserSession->id);
        $total     = $this->QueueModel->getTotalQueueItemList($checkQueue->id, $checkUserSession->id);
        $totalPage = ceil($total / $limit);

        $list = [
            'lists'      => $data,
            'total_page' => (string) $totalPage,
            'page'       => (string) $page,
            'limit'      => $limit,
        ];
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User view queue item',
            moduleId: RECEIVING_MODULE,
            action: 1,
            idAffected: 0,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Basic.SuccessDataretrived'), $list);

        return $this->respond($response, $response['status']);
    }

    public function resetQty($queue_uuid)
    {
        $validation = \Config\Services::validation();
        $userId     = $this->request?->auth_detail?->user_id;
        $parkingBay = $this->request->getPost('parking_bay');
        $data       = [
            'queue_uuid'   => $queue_uuid,
            'session_uuid' => $this->request->getPost('session_uuid'),
        ];

        if (! $validation->run($data, 'resetqty')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $session_uuid  = $validatedData['session_uuid'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);
        $checkQueue       = $this->QueueModel->getQueueInfo($queue_uuid);

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'receiving_sessions.user_sessions_id' => $checkUserSession->id,
            'receiving_sessions.queue_id'         => $checkQueue->id,
        ];

        $ReceivingSessionId = $this->ReceivingSessionModel->getReceivingSessionId(
            conditions: $whereCondition,
            selectColumn: [
                'receiving_sessions.id as receivingId',
            ]
        );
        $receivingIds = [];

        foreach ($ReceivingSessionId as $dataObject) {
            $receivingIds[] = $dataObject->receivingId;
        }
        $serialNumbersIds = $this->SerialNumbersStatusModel->whereIn('receiving_sessions_id', $receivingIds)->findColumn('serial_numbers_id');
        if (! empty($serialNumbersIds)) {
            $setColumns = [];

            foreach ($serialNumbersIds as $data) {
                $setColumns[] = [
                    'id'     => $data,
                    'active' => INACTIVE,
                ];
            }
            $updateSerialNumber = $this->SerialNumbersModel
                ->updateBatch($setColumns, 'id');
        }

        $whereCondition = [
            'user_sessions_id' => $checkUserSession->id,
            'queue_id'         => $checkQueue->id,
        ];
        $setColumn = [
            'qty' => 0,
        ];

        $updateReceivingSessionQty = $this->ReceivingSessionModel->resetReceivingSessionQty($whereCondition, $setColumn);
        if (! $updateReceivingSessionQty) {
            $response = responseFormater(500, lang('Receiving.FailedUpdateQty'));

            return $this->respond($response, $response['status']);
        }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User reset quantity',
            moduleId: RECEIVING_MODULE,
            action: 2,
            idAffected: 0,
            recordAffected: $receivingIds
        );
        $response = responseFormater(200, lang('Receiving.ResetQty'));

        return $this->respond($response, $response['status']);
    }

    public function scanBarcode($queue_uuid)
    {
        $validation = \Config\Services::validation();
        $userId     = $this->request?->auth_detail?->user_id;
        $data       = [
            'queue_uuid'   => $queue_uuid,
            'barcode'      => $this->request->getPost('barcode'),
            'qty'          => $this->request->getPost('qty'),
            'session_uuid' => $this->request->getPost('session_uuid'),
            'parking_bay'  => $this->request->getPost('parking_bay'),
        ];

        if (! $validation->run($data, 'barcode')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $barcode       = $validatedData['barcode'];
        $qty           = $validatedData['qty'];
        $session_uuid  = $validatedData['session_uuid'];
        $parkingBay    = $validatedData['parking_bay'];

        $checkUserSession   = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);
        $parkingbayId       = $this->ParkingBayModel->getParkingBaysidByUuid($parkingBay, RECEIVING);
        $queueInfoCondition = [
            'active'     => ACTIVE,
            'queue_uuid' => $queue_uuid,
        ];
        $checkQueue = $this->QueueModel->getQueueInfo($queue_uuid);

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $parkingbayId) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));

            return $this->respond($response, $response['status']);
        }

        $whereCondition = [
            'receiving_sessions.user_sessions_id' => $checkUserSession->id,
            'receiving_sessions.barcode'          => $barcode,
        ];

        $checkReceivingSession = $this->ReceivingSessionModel->checkReceivingSession(
            conditions: $whereCondition,
            selectColumn: [
                'receiving_sessions.qty',
                'receiving_sessions.id as receivingId',
            ]
        );

        $userId     = $this->request?->auth_detail?->user_id;
        $checkQueue = $this->QueueModel->getQueueInfo($queue_uuid);
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.UserQueueNotFound'));

            return $this->respond($response, $response['status']);
        }

        $ProductInfo = $this->ProductModel->getProductInfo(
            $barcode,
            selectColumn: [
                'id',
                'required_serial_number',
            ]
        );
        if (! $ProductInfo) {
            $response = responseFormater(400, lang('Basic.ProductInfoNotFound'));

            return $this->respond($response, $response['status']);
        }
        if ($ProductInfo->required_serial_number === 1) {
            if (! $checkReceivingSession) {
                $dataItem = [
                    'user_sessions_id' => $checkUserSession->id,
                    'queue_id'         => $checkQueue->id,
                    'parking_bay'      => $parkingbayId->id,
                    'barcode'          => $barcode,
                    'product_id'       => $ProductInfo->id,
                    'qty'              => 0,
                    'status'           => ACTIVE,
                    'created_at'       => getDateTime(),
                ];
                $insertReceivingSession = $this->ReceivingSessionModel->insert($dataItem);
                $insertedId             = $this->ReceivingSessionModel->getInsertID();
                if (! $insertReceivingSession) {
                    $response = responseFormater(400, lang('Basic.FailedScanItem'));

                    return $this->respond($response, $response['status']);
                }
                $data = [
                    'required_serial_number' => 'true',
                ];
                $this->AppActivityLogModel->logActivity(
                    userId: $userId,
                    actionDescription: 'User scan barcode',
                    moduleId: RECEIVING_MODULE,
                    action: 1,
                    idAffected: $insertedId,
                    recordAffected: $dataItem
                );
                $response = responseFormater(200, lang('Basic.itemNeedSerialNumber'), $data);

                return $this->respond($response, $response['status']);
            }
            $response = responseFormater(400, lang('Basic.itemAlreadyBeScanned'));

            return $this->respond($response, $response['status']); 
        }

        $receivingItemInfoCondition = [
            'receiving_items.product_id' => $ProductInfo->id,
            'receiving_lists.queue_id'   => $checkQueue->id,
        ];
        $receivingInfo = $this->ReceivingItemModel->getReceivingItemInfo(
            conditions: $receivingItemInfoCondition,
            selectColumn: [
                'qty',
            ]
        );
        if (! $receivingInfo) {
            $response = responseFormater(400, lang('Receiving.ReceivingItemNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkReceivingSession) {
            $dataItem = [
                'user_sessions_id' => $checkUserSession->id,
                'queue_id'         => $checkQueue->id,
                'parking_bay'      => $parkingbayId->id,
                'barcode'          => $barcode,
                'product_id'       => $ProductInfo->id,
                'qty'              => $qty,
                'status'           => ACTIVE,
                'created_at'       => getDateTime(),
            ];
            $insertReceivingSession = $this->ReceivingSessionModel->insert($dataItem);
            $insertedId             = $this->ReceivingSessionModel->getInsertID();
            if (! $insertReceivingSession) {
                $response = responseFormater(400, lang('Basic.FailedScanItem'));

                return $this->respond($response, $response['status']);
            }
            $response = responseFormater(200, lang('Basic.SuccessScanItem'));

            return $this->respond($response, $response['status']);
        }
        $dataItem = [];
        if (($checkReceivingSession->qty + $qty) > $receivingInfo->qty) {
            $response = responseFormater(400, lang('Basic.QuantityOverLimit'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'receiving_sessions.id'      => $checkReceivingSession->receivingId,
            'receiving_sessions.barcode' => $barcode,
        ];

        $updateReceivingSession = $this->ReceivingSessionModel->updateReceivingSession(
            $qty,
            conditions: $whereCondition,
        );
        $insertedId = $this->ReceivingSessionModel->getInsertID();

        if (! $updateReceivingSession) {
            $response = responseFormater(400, lang('Basic.FailedScanItem'));

            return $this->respond($response, $response['status']);
        }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User scan barcode',
            moduleId: RECEIVING_MODULE,
            action: 1,
            idAffected: $insertedId,
            recordAffected: $dataItem
        );
        $response = responseFormater(200, lang('Basic.SuccessScanItem'));

        return $this->respond($response, $response['status']);
    }

    public function submitReceiving($queue_uuid)// password-asd
    {
        $validation = \Config\Services::validation();
        $userId     = $this->request?->auth_detail?->user_id;
        $code       = $this->request->getPost('code');
        $data       = [
            'queue_uuid'   => $queue_uuid,
            'session_uuid' => $this->request->getPost('session_uuid'),
            'parking_bay'  => $this->request->getPost('parking_bay'),
        ];

        if (! $validation->run($data, 'submitReceiving')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData    = $validation->getValidated();
        $queue_uuid       = $validatedData['queue_uuid'];
        $session_uuid     = $validatedData['session_uuid'];
        $parkingBay       = $validatedData['parking_bay'];
        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);

        $checkQueue      = $this->QueueModel->getQueueInfo($queue_uuid);
        $checkParkingBay = $this->ParkingBayModel->checkParkingBay($parkingBay, RECEIVING);
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkParkingBay) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));

            return $this->respond($response, $response['status']);
        }

        $quantitydiff = $this->QueueModel->getQuantitydiff($checkQueue->id, $checkUserSession->id);
        // check quantity total from receiving session and receiving item
        $response        = '0';
        $idArray         = [];
        $totalReceiving  = 0;
        $receivingStatus = RECEIVING_COMPLETED;
        if ($quantitydiff->sumQty !== $quantitydiff->sumRqty) {
            $response        = '1';
            $receivingStatus = RECEIVING_COMPLETED_DIFF;
        }

        $totalReceiving = $quantitydiff->sumRqty;
        // if same quantity
        if (! $response) {
            $queueItem = $this->QueueModel->getQueueItem($checkQueue->id, $checkUserSession->id);

            foreach ($queueItem as $row) {
                $idArray[] = ['id' => $row['id'], 'rqty' => $row['rqty'], 'product_id' => $row['product_id']];
            }

            $putawayCondition = [
                'date' => getTodayDate(),
            ];
            $checkPutawayDate = $this->PutawayModel->getPutawayInfo(
                conditions: $putawayCondition,
                selectColumn: [
                    'id',
                ]
            );
            $ulid = Ulid::generate(true);

            if (! $checkPutawayDate) {
                $dataPutaway = [
                    'putaway_uuid'   => $ulid,
                    'date'           => getTodayDate(),
                    'total_received' => $totalReceiving,
                    'total_putaway'  => 0,
                    'created_at'     => getDateTime(),
                    'created_by'     => $userId,
                    'active'         => ACTIVE,
                ];

                $insertPutawayDate = $this->PutawayModel->insert($dataPutaway);
                $putawayId         = $insertPutawayDate;
                if (! $insertPutawayDate) {
                    $response = responseFormater(500, lang('Receiving.FailedCreateData'));

                    return $this->respond($response, $response['status']);
                }
            } else {
                $putawayId      = $checkPutawayDate->id;
                $whereCondition = [
                    'id' => $checkPutawayDate->id,
                ];

                $updatePutawayTotalReceiving = $this->PutawayModel->updatePutawayitem($totalReceiving, $whereCondition);
                if (! $updatePutawayTotalReceiving) {
                    $response = responseFormater(500, lang('Receiving.failedSubmitReceiving'));

                    return $this->respond($response, $response['status']);
                }
            }

            $setColumns = [];

            foreach ($idArray as $data) {
                $setColumns[] = [
                    'id'                 => $data['id'],
                    'receiving_qty'      => $data['rqty'],
                    'receiving_staff'    => $userId,
                    'receiving_datetime' => getDateTime(),
                ];
            }

            $updateReceivingItem = $this->ReceivingItemModel
                ->updateBatch($setColumns, 'id');

            if (! $updateReceivingItem) {
                $response = responseFormater(500, lang('Receiving.FailedUpdateItemStatus'));

                return $this->respond($response, $response['status']);
            }
            // pass
            $setColumns = [];

            foreach ($idArray as $data) {
                if ($data['rqty']) {
                    $setColumns[] = [
                        'putaway_date_id' => $putawayId,
                        'products_id'     => $data['product_id'],
                        'receiving_qty'   => $data['rqty'],
                        'putaway_qty'     => 0,
                        'parking_bays_id' => $checkParkingBay->id,
                        'created_at'      => getDateTime(),
                        'created_by'      => $userId,
                        'active'          => ACTIVE,
                    ];
                }
            }
            $insertPutawayItem = $this->PutawayItemModel->insertBatch($setColumns);

            if (! $insertPutawayItem) {
                $response = responseFormater(500, lang('Receiving.FailedCreateData'));

                return $this->respond($response, $response['status']);
            }
            $updateAll = $this->UpdateStatusModel->UpdateStatus($checkQueue->id, $receivingStatus, INACTIVE, $userId, $checkUserSession->id);
            if (! $updateAll) {
                $response = responseFormater(500, lang('Receiving.failedSubmitReceiving'));

                return $this->respond($response, $response['status']);
            }
            $response = responseFormater(200, lang('Receiving.SuccessSubmitReceiving'));

            return $this->respond($response, $response['status']);
        }
        // quantity not same
        if (! $code) {
            $response = responseFormater(400, lang('Receiving.QtyNotMatch'));

            return $this->respond($response, $response['status']);
        }
        $userInfoCondition = [
            'id' => $userId,
        ];
        $userInfo = $this->UserModel->getUserInfo(
            conditions: $userInfoCondition,
            selectColumn: [
                'position',
                'platform_types_id',
                'password',
            ]
        );
        $position          = $userInfo->position;
        $platform_types_id = $userInfo->platform_types_id;
        $password          = $userInfo->password;

        if ($position === SUPERVISOR) {
            if ($password !== encryptor('encrypt', $code)) {
                $response = responseFormater(400, lang('Receiving.InvalidPassword'));

                return $this->respond($response, $response['status']);
            }
            $approvalUser = $userId;
        } elseif ($position === USER) {// user : 4
            $encApprovalCode = encryptor('encrypt', $code);
            $approvalCode    = $this->ApprovalCodeModel->getApprovalCode($encApprovalCode);
            if (! $approvalCode) {
                $response = responseFormater(400, lang('Receiving.InvalidCode'));

                return $this->respond($response, $response['status']);
            }
            $whereConditionCode = [
                'id' => $approvalCode->id,
            ];
            $setColumnCode = [
                'used_by' => $userId,
                'active'  => INACTIVE,
                'used_on' => getDateTime(),
            ];
            $updateApprovalCode = $this->ApprovalCodeModel->update($whereConditionCode, $setColumnCode);
            if (! $updateApprovalCode) {
                $response = responseFormater(500, lang('Receiving.FailedUpdateApprovalCodeData'));

                return $this->respond($response, $response['status']);
            }
            $approvalUser = $approvalCode->users_id;
        }
        $queueItem = $this->QueueModel->getQueueItem($checkQueue->id, $checkUserSession->id);

        foreach ($queueItem as $row) {
            $idArray[] = ['id' => $row['id'], 'rqty' => $row['rqty'], 'product_id' => $row['product_id']];
        }

        $putawayCondition = [
            'date' => getTodayDate(),
        ];
        $checkPutawayDate = $this->PutawayModel->getPutawayInfo(
            conditions: $putawayCondition,
            selectColumn: [
                'id',
            ]
        );
        $ulid = Ulid::generate(true);

        if (! $checkPutawayDate) {
            $dataPutaway = [
                'putaway_uuid'   => $ulid,
                'date'           => getTodayDate(),
                'total_received' => $totalReceiving,
                'total_putaway'  => 0,
                'created_at'     => getDateTime(),
                'created_by'     => $userId,
                'active'         => ACTIVE,
            ];

            $insertPutawayDate = $this->PutawayModel->insert($dataPutaway);
            $putawayId         = $insertPutawayDate;
            if (! $insertPutawayDate) {
                $response = responseFormater(500, lang('Receiving.FailedCreateData'));

                return $this->respond($response, $response['status']);
            }
        } else {
            $putawayId      = $checkPutawayDate->id;
            $whereCondition = [
                'id' => $checkPutawayDate->id,
            ];

            $updatePutawayTotalReceiving = $this->PutawayModel->updatePutawayitem($totalReceiving, $whereCondition);
            if (! $updatePutawayTotalReceiving) {
                $response = responseFormater(500, lang('Receiving.failedSubmitReceiving'));

                return $this->respond($response, $response['status']);
            }
        }

        $setColumns = [];

        foreach ($idArray as $data) {
            $setColumns[] = [
                'id'                 => $data['id'],
                'receiving_qty'      => $data['rqty'],
                'receiving_staff'    => $userId,
                'receiving_datetime' => getDateTime(),
                'receiving_approval' => $approvalUser,
            ];
        }

        $updateReceivingItem = $this->ReceivingItemModel
            ->updateBatch($setColumns, 'id');

        if (! $updateReceivingItem) {
            $response = responseFormater(500, lang('Receiving.FailedUpdateItemStatus'));

            return $this->respond($response, $response['status']);
        }
        // pass
        $setColumns = [];

        foreach ($idArray as $data) {
            if ($data['rqty']) {
                $setColumns[] = [
                    'putaway_date_id' => $putawayId,
                    'products_id'     => $data['product_id'],
                    'receiving_qty'   => $data['rqty'],
                    'putaway_qty'     => 0,
                    'parking_bays_id' => $checkParkingBay->id,
                    'created_at'      => getDateTime(),
                    'created_by'      => $userId,
                    'active'          => ACTIVE,
                ];
            }
        }
        $insertPutawayItem = $this->PutawayItemModel->insertBatch($setColumns);

        if (! $insertPutawayItem) {
            $response = responseFormater(500, lang('Receiving.FailedCreateData'));

            return $this->respond($response, $response['status']);
        }
        $updateAll = $this->UpdateStatusModel->UpdateStatus($checkQueue->id, $receivingStatus, INACTIVE, $userId, $checkUserSession->id);
        if (! $updateAll) {
            $response = responseFormater(500, lang('Receiving.failedSubmitReceiving'));

            return $this->respond($response, $response['status']);
        }
        $response = responseFormater(200, lang('Receiving.SuccessSubmitReceiving'));

        return $this->respond($response, $response['status']);
    }

    public function updateQty($queue_uuid)
    {
        $validation = \Config\Services::validation();
        $userId     = $this->request?->auth_detail?->user_id;
        $parkingBay = $this->request->getPost('parking_bay');
        $data       = [
            'queue_uuid'    => $queue_uuid,
            'session_uuid'  => $this->request->getPost('session_uuid'),
            'products_uuid' => $this->request->getPost('products_uuid'),
            'qty'           => $this->request->getPost('qty'),
        ];

        if (! $validation->run($data, 'updateqty')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $session_uuid  = $validatedData['session_uuid'];
        $products_uuid = $validatedData['products_uuid'];
        $qty           = $validatedData['qty'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);
        $checkQueue       = $this->QueueModel->getQueueInfo($queue_uuid);
        $checkParkingBay  = $this->ParkingBayModel->checkParkingBay($parkingBay, RECEIVING);
        $ProductInfo      = $this->ProductModel->getProductByProductUuid(
            $products_uuid,
            selectColumn: [
                'id',
            ]
        );
        if (! $ProductInfo) {
            $response = responseFormater(400, lang('Basic.ProductInfoNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'user_sessions_id' => $checkUserSession->id,
            'queue_id'         => $checkQueue->id,
            'product_id'       => $ProductInfo->id,
        ];
        $receivingItemInfoCondition = [
            'receiving_items.product_id' => $ProductInfo->id,
            'receiving_lists.queue_id'   => $checkQueue->id,
        ];
        $receivingInfo = $this->ReceivingItemModel->getReceivingItemInfo(
            conditions: $receivingItemInfoCondition,
            selectColumn: [
                'qty',
            ]
        );

        if (($qty) > $receivingInfo->qty) {
            $response = responseFormater(400, lang('Basic.QuantityOverLimit'));

            return $this->respond($response, $response['status']);
        }
        $setColumn = [
            'qty' => $qty,
        ];
        $checkReceivingSession = $this->ReceivingSessionModel->checkReceivingSession(
            conditions: $whereCondition,
            selectColumn: [
                'receiving_sessions.id',
            ]
        );
        $whereCondition = [
            'id' => $checkReceivingSession->id,
        ];
        $updateReceivingSessionQty = $this->ReceivingSessionModel->update($whereCondition, $setColumn);
        if (! $updateReceivingSessionQty) {
            $response = responseFormater(500, lang('Receiving.failedUpdateQty'));

            return $this->respond($response, $response['status']);
        }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User Update quantity',
            moduleId: RECEIVING_MODULE,
            action: 2,
            idAffected: $checkReceivingSession->id,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Basic.SuccessUpdateQty'));

        return $this->respond($response, $response['status']);
    }

    public function scanSerialNumber($queue_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'queue_uuid'    => $queue_uuid,
            'products_uuid' => $this->request->getPost('products_uuid'),
            'serial_number' => $this->request->getPost('serial_number'),
            'session_uuid'  => $this->request->getPost('session_uuid'),
            'parking_bay'   => $this->request->getPost('parking_bay'),
        ];
        if (! $validation->run($data, 'scanSerialNumber')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $session_uuid  = $validatedData['session_uuid'];
        $products_uuid = $validatedData['products_uuid'];
        $serialNumber  = $validatedData['serial_number'];
        $parkingBay    = $validatedData['parking_bay'];

        $checkParkingBay   = $this->ParkingBayModel->checkParkingBay($parkingBay, RECEIVING);
        $checkUserSession  = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);
        $checkQueue        = $this->QueueModel->getQueueInfo($queue_uuid);
        $checkSerialNumber = $this->SerialNumbersModel->getSerialNumber($serialNumber);

        $ProductInfo = $this->ProductModel->getProductByProductUuid(
            $products_uuid,
            selectColumn: [
                'id',
            ]
        );
        $receivingItemInfoCondition = [
            'receiving_items.product_id' => $ProductInfo->id,
            'receiving_lists.queue_id'   => $checkQueue->id,
        ];
        $receivingInfo = $this->ReceivingItemModel->getReceivingItemInfo(
            conditions: $receivingItemInfoCondition,
            selectColumn: [
                'receiving_items.id',
                'receiving_items.qty',
            ]
        );

        if (! $receivingInfo) {
            $response = responseFormater(400, lang('Receiving.ReceivingItemNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $ProductInfo) {
            $response = responseFormater(400, lang('Basic.ProductInfoNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        if ($checkSerialNumber) {
            $response = responseFormater(400, lang('Receiving.DuplicateSerialNumber'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'receiving_sessions.user_sessions_id' => $checkUserSession->id,
            'receiving_sessions.product_id'       => $ProductInfo->id,
            'receiving_sessions.queue_id'         => $checkQueue->id,
        ];
        $checkReceivingSession = $this->ReceivingSessionModel->checkReceivingSession(
            conditions: $whereCondition,
            selectColumn: [
                'receiving_sessions.id as receivingId',
                'receiving_sessions.qty as qty',
            ]
        );

        $ulid = Ulid::generate(true);
        $data = [
            'products_id'         => $ProductInfo->id,
            'serial_numbers_uuid' => $ulid,
            'serial_number'       => $serialNumber,
            'created_at'          => getDateTime(),
            'created_by'          => $userId,
            'active'              => '1',
        ];
        $this->SerialNumbersModel->insert($data);
        $insertSerialNumbers = $this->SerialNumbersModel->getInsertID();

        $data = [
            'serial_numbers_id'     => $insertSerialNumbers,
            'receiving_items_id'    => $receivingInfo->id,
            'receiving_sessions_id' => $checkReceivingSession->receivingId,
        ];
        if (! $insertSerialNumbers) {
            $response = responseFormater(400, lang('Basic.FailedScanSerialNumber'));

            return $this->respond($response, $response['status']);
        }
        $insertSerialNumbersStatus = $this->SerialNumbersStatusModel->insert($data);

        if (! $insertSerialNumbersStatus) {
            $response = responseFormater(400, lang('Basic.FailedScanSerialNumber'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'receiving_sessions.id'         => $checkReceivingSession->receivingId,
            'receiving_sessions.product_id' => $ProductInfo->id,
        ];

        if (($checkReceivingSession->qty + 1) > $receivingInfo->qty) {
            $response = responseFormater(400, lang('Basic.QuantityOverLimit'));

            return $this->respond($response, $response['status']);
        }
        $updateReceivingSession = $this->ReceivingSessionModel->updateReceivingSession(
            '1',
            conditions: $whereCondition,
        );
        if (! $updateReceivingSession) {
            $response = responseFormater(400, lang('Basic.FailedScanSerialNumber'));

            return $this->respond($response, $response['status']);
        }
        $response = responseFormater(200, lang('Basic.SuccessSerialNumber'));

        return $this->respond($response, $response['status']);
    }

    public function serialNumberList($queue_uuid, $products_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'queue_uuid'    => $queue_uuid,
            'products_uuid' => $products_uuid,
            'session_uuid'  => $this->request->getGet('session_uuid'),
            'limit'         => $this->request->getGet('limit'),
            'page'          => $this->request->getGet('page'),
        ];
        if (! $validation->run($data, 'SerialNumberList')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData = $validation->getValidated();
        $queue_uuid    = $validatedData['queue_uuid'];
        $products_uuid = $validatedData['products_uuid'];
        $session_uuid  = $validatedData['session_uuid'];
        $limit         = $validatedData['limit'];
        $page          = $validatedData['page'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);
        $checkQueue       = $this->QueueModel->getQueueInfo($queue_uuid);
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        $page   = (int) $page;
        $offset = ($page - 1) * $limit;

        $data      = $this->SerialNumbersModel->getSerialNumberList($offset, $limit, $products_uuid, $checkUserSession->id, $checkQueue->id);
        $total     = $this->SerialNumbersModel->getTotalSerialNumber($products_uuid, $checkUserSession->id, $checkQueue->id);
        $totalPage = ceil($total / $limit);
        $lists     = [];
        $details   = [];
        if ($data->serial_numbers_uuid) {
            $lists = [
                [
                    'serial_number_uuid' => $data->serial_numbers_uuid,
                    'serial_number'      => $data->serial_number,
                ],
            ];

            $details = [
                'product_uuid' => $data->products_uuid,
                'sku'          => $data->sku,
                'description'  => $data->description,
                'total_qty'    => $data->total_qty,
            ];
        }
        $list = [
            'lists'      => $lists,
            'details'    => $details,
            'total_page' => (string) $totalPage,
            'page'       => (string) $page,
            'limit'      => $limit,
        ];
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User view serial number list',
            moduleId: RECEIVING_MODULE,
            action: 1,
            idAffected: 0,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Basic.SuccessDataretrived'), $list);

        return $this->respond($response, $response['status']);
    }

    public function deleteSerialNumber($queue_uuid, $products_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'queue_uuid'         => $queue_uuid,
            'products_uuid'      => $products_uuid,
            'session_uuid'       => $this->request->getPost('session_uuid'),
            'serial_number_uuid' => $this->request->getPost('serial_number_uuid'),
        ];
        if (! $validation->run($data, 'deleteSerialNumber')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData      = $validation->getValidated();
        $queue_uuid         = $validatedData['queue_uuid'];
        $products_uuid      = $validatedData['products_uuid'];
        $session_uuid       = $validatedData['session_uuid'];
        $serial_number_uuid = $validatedData['serial_number_uuid'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, RECEIVING_MODULE, $userId);
        $checkQueue       = $this->QueueModel->getQueueInfo($queue_uuid);
        $ProductInfo      = $this->ProductModel->getProductByProductUuid(
            $products_uuid,
            selectColumn: [
                'id',
            ]
        );

        $SerialNumberId = $this->SerialNumbersModel->getSerialNumberByUuid(
            $serial_number_uuid,
            selectColumn: [
                'id',
            ]
        );

        if (! $ProductInfo) {
            $response = responseFormater(400, lang('Basic.ProductInfoNotFound'));

            return $this->respond($response, $response['status']);
        }

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkQueue) {
            $response = responseFormater(400, lang('Receiving.QueueNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $SerialNumberId) {
            $response = responseFormater(400, lang('Basic.SerialNumberNotFound'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'id' => $SerialNumberId->id,
        ];
        $setColumn = [
            'active' => INACTIVE,
        ];
        $updateSerialNumber = $this->SerialNumbersModel->update($whereCondition, $setColumn);
        if (! $updateSerialNumber) {
            $response = responseFormater(400, lang('Receiving.FailedDeleteSerialNumber'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'receiving_sessions.product_id'       => $ProductInfo->id,
            'receiving_sessions.user_sessions_id' => $checkUserSession->id,
            'receiving_sessions.queue_id'         => $checkQueue->id,
        ];

        $updateReceivingSession = $this->ReceivingSessionModel->updateReceivingSession(
            '-1',
            conditions: $whereCondition,
        );
        if (! $updateReceivingSession) {
            $response = responseFormater(400, lang('Receiving.FailedDeleteSerialNumber'));

            return $this->respond($response, $response['status']);
        }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User delete serial number',
            moduleId: RECEIVING_MODULE,
            action: 3,
            idAffected: $SerialNumberId->id,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Receiving.SuccessDeleteSerialNumber'));

        return $this->respond($response, $response['status']);
    }
}
