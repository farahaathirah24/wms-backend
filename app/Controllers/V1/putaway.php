<?php

namespace App\Controllers\V1\App;

use App\Controllers\BaseController;
use App\Models\V1\App\AppActivityLogModel;
use App\Models\V1\App\PutawayItemModel;
use App\Models\V1\App\PutawayModel;
use App\Models\V1\App\PutawaySessionModel;
use App\Models\V1\ParkingBayModel;
use App\Models\V1\RacksModel;
use App\Models\V1\UserSessionsModel;
use App\Models\V1\ProductModel;
use App\Models\V1\SerialNumbersModel;
use App\Models\V1\SerialNumbersStatusModel;
use App\Models\V1\App\PutawaySessionsSerialNumbersModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Ulid\Ulid;

class Putaway extends BaseController
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
        $this->PutawayModel        = new PutawayModel();
        $this->PutawayItemModel    = new PutawayItemModel();
        $this->UserSessionsModel   = new UserSessionsModel();
        $this->ParkingBayModel     = new ParkingBayModel();
        $this->PutawaySessionModel = new PutawaySessionModel();
        $this->RacksModel          = new RacksModel();
        $this->AppActivityLogModel      = new AppActivityLogModel();
        $this->ProductModel             = new ProductModel();
        $this->SerialNumbersModel       = new SerialNumbersModel();
        $this->SerialNumbersStatusModel = new SerialNumbersStatusModel();
        $this->PutawaySessionsSerialNumbersModel = new PutawaySessionsSerialNumbersModel();
    }

    public function scanParkingBay()
    {
        $validation = \Config\Services::validation();
        $data       = [
            'parking_bay' => $this->request->getPost('parking_bay'),
        ];
        $rules = [
            'parking_bay' => 'required|string',
        ];
        if (! $this->validateData($data, $rules)) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData   = $validation->getValidated();
        $parkingBay      = $validatedData['parking_bay'];
        $checkParkingBay = $this->ParkingBayModel->checkParkingBay($parkingBay, RECEIVING);
        if (! $checkParkingBay) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));

            return $this->respond($response, $response['status']);
        }
        $ParkingBaysUuid = $this->ParkingBayModel->getParkingBaysUuid($parkingBay, RECEIVING);
        $ulid = Ulid::generate(true);

        $userId = $this->request?->auth_detail?->user_id;
        $data   = [
            'session_uuid'   => $ulid,
            'users_id'       => $userId,
            'sub_modules_id' => PUTAWAY_MODULE,
            'status'         => USER_ACTIVE,
            'created_at'     => getDateTime(),
            'location_in'    => $checkParkingBay->id,
        ];
        $checkUserSession = $this->UserSessionsModel->checkUserSession('', PUTAWAY_MODULE, $userId);
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
        $item     = [
            'session_uuid' => (string) $ulid,
            'parking_bay_uuid' => (string) $ParkingBaysUuid->parking_bays_uuid,
        ];
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User scan parking bay',
            moduleId: PUTAWAY_MODULE,
            action: 0,
            idAffected: $insertedId,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Receiving.SuccessScanParkingBay'), $item);

        return $this->respond($response, $response['status']);
    }

    public function putawayItem($session_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'session_uuid' => $session_uuid,
            'parking_bay'  => $this->request->getGet('parking_bay'),
            'limit'        => $this->request->getGet('limit'),
            'page'         => $this->request->getGet('page'),
        ];
        if (! $validation->run($data, 'putawayItem')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData = $validation->getValidated();
        $session_uuid  = $validatedData['session_uuid'];
        $parkingBay    = $validatedData['parking_bay'];
        $limit         = $validatedData['limit'];
        $page          = $validatedData['page'];

        $parkingbayId       = $this->ParkingBayModel->getParkingBaysidByUuid($parkingBay, RECEIVING);
        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, PUTAWAY_MODULE, $userId);
        if (! $parkingbayId) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Basic.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        $page   = (int) $page;
        $offset = ($page - 1) * $limit;

        $data      = $this->PutawaySessionModel->getPutawayList($limit, $offset, $checkUserSession->id);
        $total     = $this->PutawaySessionModel->getTotalPutawayList($checkUserSession->id);
        $totalPage = ceil($total / $limit);
        $list      = [
            'list'       => $data,
            'total_page' => (string) $totalPage,
            'page'       => (string) $page,
            'limit'      => $limit,
        ];
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User view putaway list',
            moduleId: PUTAWAY_MODULE,
            action: 1,
            idAffected: 0,
            recordAffected: []
        );

        $response = responseFormater(200, lang('Receiving.SuccessDataretrived'), $list);

        return $this->respond($response, $response['status']);
    }

    public function scanBarcode($session_uuid) 
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'barcode'      => $this->request->getPost('barcode'),
            'qty'          => $this->request->getPost('qty'),
            'parking_bay'  => $this->request->getPost('parking_bay'),
            'session_uuid' => $session_uuid,

        ];
        if (! $validation->run($data, 'barcodePutaway')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData = $validation->getValidated();
        $barcode       = $validatedData['barcode'];
        $qty           = $validatedData['qty'];
        $parkingBay    = $validatedData['parking_bay'];
        $session_uuid  = $validatedData['session_uuid'];
        $checkUserSession   = $this->UserSessionsModel->checkUserSession($session_uuid, PUTAWAY_MODULE, $userId);
        $parkingbayId       = $this->ParkingBayModel->getParkingBaysidByUuid($parkingBay, RECEIVING);

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Basic.UserSessionNotFound'));
            return $this->respond($response, $response['status']);
        }
        if (! $parkingbayId) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));
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
        $whereCondition = [
            'putaway_sessions.user_sessions_id' => $checkUserSession->id,
            'putaway_sessions.barcode'          => $barcode,
        ];
        $checkPutawaySession = $this->PutawaySessionModel->checkPutawaySession(
            conditions: $whereCondition,
            selectColumn: [
                'id',
                'putaway_sessions.qty',
            ]
        );
        if ($ProductInfo->required_serial_number == 1) {
            if (!$checkPutawaySession) {
                $dataItem = [
                    'user_sessions_id' => $checkUserSession->id,
                    'products_id'      => $ProductInfo->id,
                    'rack_id'          => 0,
                    'barcode'          => $barcode,
                    'qty'              => 0,
                    'status'           => ACTIVE,
                ];
                $insertputawaySession = $this->PutawaySessionModel->insert($dataItem);
                $insertedId             = $this->PutawaySessionModel->getInsertID();
                if (! $insertputawaySession) {
                    $response = responseFormater(400, lang('Basic.FailedScanItem'));

                    return $this->respond($response, $response['status']);
                }
                $data = [
                    'required_serial_number' => 'true',
                ];
                $this->AppActivityLogModel->logActivity(
                    userId: $userId,
                    actionDescription: 'User scan barcode',
                    moduleId: PUTAWAY_MODULE,
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
        $putawayCondition = [
            'date' => getTodayDate(),
        ];
        $checkPutawayDate = $this->PutawayModel->getPutawayInfo(
            conditions: $putawayCondition,
            selectColumn: [
                'id',
            ]
        );
        if (!$checkPutawayDate) {
            $response = responseFormater(400, lang('Putaway.PutawayDateNotFound'));

            return $this->respond($response, $response['status']);
        }

        $putawayItemInfoCondition = [
            'putaway_date_id' => $checkPutawayDate->id,
            'putaway_item.products_id' => $ProductInfo->id,
            'putaway_item.parking_bays_id'   => $parkingbayId->id,
        ];
        $putawayItem = $this->PutawayItemModel->getPutawayItem(
            conditions: $putawayItemInfoCondition,
            selectColumn: [
                'receiving_qty',
            ]
        );
     
        if (!$putawayItem) {
            $response = responseFormater(400, lang('Putaway.PutawayItemNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $checkPutawaySession) {
                $dataItem = [
                    'user_sessions_id' => $checkUserSession->id,
                    'products_id'      => $ProductInfo->id,
                    'rack_id'          => 0,
                    'barcode'          => $barcode,
                    'qty'              => $qty,
                    'status'           => ACTIVE,
                ];
                $insertputawaySession = $this->PutawaySessionModel->insert($dataItem);
                $insertedId             = $this->PutawaySessionModel->getInsertID();
                if (! $insertputawaySession) {
                    $response = responseFormater(400, lang('Basic.FailedScanItem'));

                    return $this->respond($response, $response['status']);
                }
               $response = responseFormater(200, lang('Basic.SuccessScanItem'));

               return $this->respond($response, $response['status']);
        }
        $dataItem = [];
        if (($checkPutawaySession->qty + $qty) > $putawayItem->receiving_qty) {
            $response = responseFormater(400, lang('Basic.QuantityOverLimit'));

            return $this->respond($response, $response['status']);
        }

        $whereCondition = [
            'putaway_sessions.id'      => $checkPutawaySession->id,
        ];

        $updatePutawaySession = $this->PutawaySessionModel->updatePutawaySession(
            $qty,
            conditions: $whereCondition,
        );
        if (! $updatePutawaySession) {
            $response = responseFormater(400, lang('Basic.FailedScanItem'));

            return $this->respond($response, $response['status']);
        }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User scan barcode',
            moduleId: PUTAWAY_MODULE,
            action: 1,
            idAffected: $checkPutawaySession->id,
            recordAffected: $dataItem
        );
        $response = responseFormater(200, lang('Basic.SuccessScanItem'));

        return $this->respond($response, $response['status']);

    }

    public function resetQty($session_uuid)
    {
        $validation = \Config\Services::validation();
        $userId     = $this->request?->auth_detail?->user_id;
        $rackIn     = $this->request->getPost('rack_in');
        $data       = [
            'session_uuid' => $session_uuid,
            'parking_bay'  => $this->request->getPost('parking_bay'),
        ];

        if (! $validation->run($data, 'putawayResetqty')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData = $validation->getValidated();
        $session_uuid  = $validatedData['session_uuid'];
        $parkingBay    = $validatedData['parking_bay'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, PUTAWAY_MODULE, $userId);
        $checkParkingBay  = $this->ParkingBayModel->checkParkingBay($parkingBay, RECEIVING);

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Basic.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'user_sessions_id' => $checkUserSession->id,
        ];
        /*update putaway session*/
        $setColumn = [
            'qty' => 0,
        ];

        $updatePutawaySessionQty = $this->PutawaySessionModel->resetPutawaySessionQty($whereCondition, $setColumn);
        if (! $updatePutawaySessionQty) {
            $response = responseFormater(500, lang('Receiving.FailedUpdateQty'));

            return $this->respond($response, $response['status']);
        }
       
        /*update putaway_sessions_serial_numbers*/
     
        $PutawaySessionId = $this->PutawaySessionModel->getPutawaySessionId(
            conditions: $whereCondition,
            selectColumn: [
                'putaway_sessions.id as putawayId',
            ]
        );
        $putawayIds = [];

        foreach ($PutawaySessionId as $dataObject) {
            $putawayIds[] = $dataObject->putawayId;
        }
       
        $serialNumbersIds = $this->PutawaySessionsSerialNumbersModel->whereIn('putaway_sessions_id', $putawayIds)->findColumn('id');

        if (! empty($serialNumbersIds)) {
            $setColumns = [];

            foreach ($serialNumbersIds as $data) {
                $setColumns[] = [
                    'id'     => $data,
                    'active' => INACTIVE,
                ];
            }
            $updateSerialNumber = $this->PutawaySessionsSerialNumbersModel
                ->updateBatch($setColumns, 'id');
        }
        //end
        
        $response = responseFormater(200, lang('Receiving.ResetQty'));

        return $this->respond($response, $response['status']);
    }

    public function scanRackIn($session_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'session_uuid' => $session_uuid,
            'rack_in'      => $this->request->getPost('rack_in'),
        ];
        if (! $validation->run($data, 'scanRackIn')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }

        $validatedData    = $validation->getValidated();
        $session_uuid     = $validatedData['session_uuid'];
        $rackIn           = $validatedData['rack_in'];
        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, PUTAWAY_MODULE, $userId);
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Basic.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        $checkRacks = $this->RacksModel->checkRacks($rackIn);
        if (! $checkRacks) {
            $response = responseFormater(400, lang('Putaway.RackNotFound'));
        }
        $whereConditionCode = [
            'user_sessions_id' => $checkUserSession->id,
        ];
        $setColumnCode = [
            'racks_id' => $checkRacks->id,
        ];
        $updatePutawaySession = $this->PutawaySessionModel->updateRacks($whereConditionCode, $setColumnCode);
        if (! $updatePutawaySession) {
            $response = responseFormater(500, lang('Putaway.FailedScanRacks'));

            return $this->respond($response, $response['status']);
        }
        $response = responseFormater(200, lang('Putaway.SuccessScanRacks'));

        return $this->respond($response, $response['status']);
    }
    public function scanSerialNumber($session_uuid){
        $validation = \Config\Services::validation();
        $userId     = $this->request?->auth_detail?->user_id;
        $rackIn     = $this->request->getPost('rack_in');
        $data       = [
            'session_uuid' => $session_uuid,
            'parking_bay'  => $this->request->getPost('parking_bay'),
            'serial_number'  => $this->request->getPost('serial_number'),
            'products_uuid'  => $this->request->getPost('products_uuid'),
        ];
        if (! $validation->run($data, 'putawayScanSerialNumber')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData = $validation->getValidated();
        $session_uuid  = $validatedData['session_uuid'];
        $parkingBay    = $validatedData['parking_bay'];
        $serialNumber  = $validatedData['serial_number'];
        $productUuid   = $validatedData['products_uuid'];

        $checkUserSession   = $this->UserSessionsModel->checkUserSession($session_uuid, PUTAWAY_MODULE, $userId);
        $parkingbayId       = $this->ParkingBayModel->getParkingBaysidByUuid($parkingBay, RECEIVING);
        $checkSerialNumber = $this->SerialNumbersModel->getSerialNumber($serialNumber);

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Basic.UserSessionNotFound'));
            return $this->respond($response, $response['status']);
        }
        if (! $parkingbayId) {
            $response = responseFormater(400, lang('Receiving.ParkingBayNotFound'));
            return $this->respond($response, $response['status']);
        }
        if (!$checkSerialNumber) {
            $response = responseFormater(400, lang('Basic.SerialNumberNotFound'));

            return $this->respond($response, $response['status']);
        }

        $ProductInfo      = $this->ProductModel->getProductByProductUuid(
            $productUuid,
            selectColumn: [
                'id',
            ]
        );
        if (! $ProductInfo) {
            $response = responseFormater(400, lang('Basic.ProductInfoNotFound'));
            return $this->respond($response, $response['status']);
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
        if (!$checkPutawayDate) {
            $response = responseFormater(400, lang('Putaway.PutawayDateNotFound'));
            return $this->respond($response, $response['status']);
        }

        $putawayItemInfoCondition = [
            'putaway_date_id' => $checkPutawayDate->id,
            'putaway_item.products_id' => $ProductInfo->id,
            'putaway_item.parking_bays_id'   => $parkingbayId->id,
        ];
        $putawayItem = $this->PutawayItemModel->getPutawayItem(
            conditions: $putawayItemInfoCondition,
            selectColumn: [
                'id',
                'receiving_qty',
            ]
        );
        if (!$putawayItem) {
            $response = responseFormater(400, lang('Putaway.PutawayItemNotFound'));
            return $this->respond($response, $response['status']);
        }
        $whereCondition = [
            'putaway_sessions.user_sessions_id' => $checkUserSession->id,
            'putaway_sessions.products_id'          => $ProductInfo->id,
        ];
        $checkPutawaySession = $this->PutawaySessionModel->checkPutawaySession(
            conditions: $whereCondition,
            selectColumn: [
                'id',
                'putaway_sessions.qty',
            ]
        );
        if (! $checkPutawaySession) {
            $response = responseFormater(400, lang('Putaway.PutawaySessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        // check limitation quantity 
        if (($checkPutawaySession->qty + 1) > $putawayItem->receiving_qty) { 
            $response = responseFormater(400, lang('Basic.QuantityOverLimit'));

            return $this->respond($response, $response['status']);
        }

        $CheckPutawaySessionsSerialNumbers = $this->PutawaySessionsSerialNumbersModel
        ->where('serial_numbers_id', $checkSerialNumber->id)
        ->where('active', ACTIVE)
        ->first();
        
        if ($CheckPutawaySessionsSerialNumbers) {
           $response = responseFormater(400, lang('Basic.SerialNumberAlreadyscanned'));
           return $this->respond($response, $response['status']);
        }

        $data   = [
            'putaway_sessions_id'   => $checkPutawaySession->id,
            'serial_numbers_id'       => $checkSerialNumber->id,
            'created_at' => getDateTime(),
            'created_by'         => $userId,
            'active'    => ACTIVE,
        ];       
        $insertPutawaySessionsSerialNumbers = $this->PutawaySessionsSerialNumbersModel->insert($data);
        if (! $insertPutawaySessionsSerialNumbers) {
            $response = responseFormater(400, lang('Basic.FailedScanSerialNumber'));
            return $this->respond($response, $response['status']);
        } 
        //update after submit
        $serialNumbersStatusId = $this->SerialNumbersStatusModel
        ->where('serial_numbers_id', $checkSerialNumber->id)
        ->first();

        // if ($serialNumbersStatusId->putaway_items_id) {
        //    $response = responseFormater(400, lang('Basic.SerialNumberAlreadyscanned'));
        //    return $this->respond($response, $response['status']);
        // }
        $whereCondition = [
            'putaway_sessions.id'      => $checkPutawaySession->id,
        ];
        $updatePutawaySession = $this->PutawaySessionModel->updatePutawaySession(
            '1',
            conditions: $whereCondition,
        );
        if (! $updatePutawaySession) {
            $response = responseFormater(400, lang('Basic.FailedScanSerialNumber'));
            return $this->respond($response, $response['status']);
        } 
        //update after submit    
        // $whereCondition = [
        //     'id' => $serialNumbersStatusId->id,
        // ];
        // $setColumn = [
        //     'putaway_items_id' => $putawayItem->id,
        // ];

        // $updateSerialNumbersStatusModel = $this->SerialNumbersStatusModel->update($whereCondition, $setColumn);
        // if (! $updatePutawaySession) {
        //     $response = responseFormater(400, lang('Basic.FailedScanSerialNumber'));
        //     return $this->respond($response, $response['status']);
        // }
        $this->AppActivityLogModel->logActivity(
            userId: $userId,
            actionDescription: 'User scan serial number',
            moduleId: PUTAWAY_MODULE,
            action: 1,
            idAffected: $serialNumbersStatusId->id,
            recordAffected: []
        );
        $response = responseFormater(200, lang('Basic.SuccessSerialNumber'));
        return $this->respond($response, $response['status']);    
    }
    
    public function serialNumberList($session_uuid, $products_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'session_uuid'    => $session_uuid,
            'products_uuid' => $products_uuid,
            'limit'         => $this->request->getGet('limit'),
            'page'          => $this->request->getGet('page'),
        ];
        if (! $validation->run($data, 'putawaySerialNumberList')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData = $validation->getValidated();
        $products_uuid = $validatedData['products_uuid'];
        $session_uuid  = $validatedData['session_uuid'];
        $limit         = $validatedData['limit'];
        $page          = $validatedData['page'];

        $checkUserSession = $this->UserSessionsModel->checkUserSession($session_uuid, PUTAWAY_MODULE, $userId);
        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Basic.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
      
        $page   = (int) $page;
        $offset = ($page - 1) * $limit;

        $dataSerialNumber = $this->SerialNumbersModel->getPutawaySerialNumber(
            $products_uuid,
            $checkUserSession->id,
            $limit,
            $offset,
            selectColumn: [
                'serial_numbers.serial_numbers_uuid',
                'serial_numbers.serial_number',
                'products.products_uuid',
                'products.sku',
                'products.description',
            ]
        );
        $total = $this->SerialNumbersModel->getTotalPutawaySerialNumber(
            $products_uuid,
            $checkUserSession->id,
            selectColumn: [
                'serial_numbers.serial_numbers_uuid',
            ]
        );
        $totalPage = ceil($total / $limit);
        $lists     = [];
        $details   = [];

        if($dataSerialNumber){
            foreach ($dataSerialNumber as $data) {
                if ($data->serial_numbers_uuid) {
                    $lists[] = [
                        'serial_numbers_uuid' => $data->serial_numbers_uuid,
                        'serial_number'      => $data->serial_number,
                    ];
                }
            }
        
            $details = [
                'products_uuid' => $data->products_uuid,
                'sku'           => $data->sku,
                'description'   => $data->description,
                'total_qty'     => (string) $total,
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
            moduleId: PUTAWAY_MODULE,
            action: 1,
            idAffected: 0,
            recordAffected: []
        );        
        $response = responseFormater(200, lang('Basic.SuccessDataretrived'), $list);

        return $this->respond($response, $response['status']);
    }

    public function deleteSerialNumber($session_uuid, $products_uuid)
    {
        $userId     = $this->request?->auth_detail?->user_id;
        $validation = \Config\Services::validation();
        $data       = [
            'session_uuid'         => $session_uuid,
            'products_uuid'      => $products_uuid,
            'serial_number_uuid' => $this->request->getPost('serial_number_uuid'),
        ];
        if (! $validation->run($data, 'deleteSerialNumber')) {
            $errors   = $validation->getErrors();
            $response = responseFormater(400, lang('Validation.uncompletedParameter'), $errors);

            return $this->respond($response, $response['status']);
        }
        $validatedData      = $validation->getValidated();
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
            $response = responseFormater(400, lang('Receiving.ProductInfoNotFound'));

            return $this->respond($response, $response['status']);
        }

        if (! $checkUserSession) {
            $response = responseFormater(400, lang('Receiving.UserSessionNotFound'));

            return $this->respond($response, $response['status']);
        }
        if (! $SerialNumberId) {
            $response = responseFormater(400, lang('Receiving.SerialNumberNotFound'));

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
