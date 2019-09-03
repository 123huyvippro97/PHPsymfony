# PHPsymfony
PHPsymfony
<?php
define("PRE_PAID", 1);
define("POST_PAID", 0);
class mobileInternetActions extends sfActions
{
    const TYPE_GET_PROMOTION_DATA_USSD = 2;
    const TYPE_GET_PROMOTION_DATA_USSD_ADDON = 3;
    const TYPE_GET_PROMOTION_DATA_USSD_PLUS = 4;

    public function executeRegisterMI(sfWebRequest $request)
    {
        call('Web.ActionLog.startTimerDebug');
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'RegisterData',
            'title' => $i18N->__('Đăng ký gói cước MI'),
            'service' => 'apiv2.mobileInternet.executeRegisterMI',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            $serviceCode = $request->getParameter('service_code', null);

            # huync2: kiem tra goi Data trong DB truoc khi dang ky
            $checkData = self::checkPackage(trim($serviceCode));

            call('Web.ActionLog.setLogDebug', array(
                'result' => array(
                    'message' => $i18N->__('Kiểm tra gói cước có trong DB'),
                    'result' => $checkData
                ),
            ));

            if ($checkData == false) {
                sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterMI goi khong hop le: ' . $serviceCode . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                $arrReturn = ApiHelper::formatResponse(
                    ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                );

                call('Web.ActionLog.setLogDebug', array(
                    'result' => array(
                        'message' => $arrReturn['message'],
                        'result' => $checkData
                    ),
                ));

                if (!empty($arrReturn)) {
                    $logFields['results'] = json_encode($arrReturn);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($arrReturn));
            }

            $logFields['inputs'] = json_encode($request);
            if ($serviceCode == null) {
                $result['errorCode'] = 3;
                $result['message'] = $i18N->__('Gói cước không đúng, vui lòng thử lại!'); //Truyền thiếu tham số service_code
                $result['data'] = null;

                call('Web.ActionLog.setLogDebug', array(
                    'result' => array(
                        'message' => $i18N->__('Truyền thiếu tham số service_code'),
                    ),
                ));

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
            //nguyetNT32: chặn 4 gói 0 mất tiền
            $arrPackege = array('MIMIN', 'MI0', 'MIMAX0', 'DC0');
            if (in_array(strtoupper($serviceCode), $arrPackege)) {
                $result['errorCode'] = 3;
                $result['message'] = $i18N->__('Gói cước này hiện không được hủy trên My Viettel, vui lòng thử lại sau!'); //Truyền thiếu tham số service_code
                $result['data'] = null;

                call('Web.ActionLog.setLogDebug', array(
                    'result' => array(
                        'message' => $result['message'],
                        'message2' => $i18N->__('chặn 4 gói 0 mất tiền'),
                    ),
                ));

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
            $client = new MobileInternetClient();

            $current_pakage = $client->checkMI($msisdn);
            if ($current_pakage == $serviceCode) {
                $result['errorCode'] = 2;
                $result['message'] = $i18N->__('Đăng ký thất bại do Quý khách đang sử dụng gói cước %package%!', array('%package%' => $current_pakage));
                $result['data'] = null;

                call('Web.ActionLog.setLogDebug', array(
                    'result' => array(
                        'message' => $result['message'],
                        'message2' => $i18N->__('chặn 4 gói 0 mất tiền'),
                    ),
                ));

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }

            $response = $client->registerMI($msisdn, $serviceCode);

            call('Web.ActionLog.setLogDebug', array(
                'result' => array(
                    'message' => 'end gọi ws registerMI',
                    'result' => $response,
                ),
            ));

            if ($response) {
                $result['errorCode'] = 0;
                $result['message'] = $i18N->__('Quý khách đã đăng ký thành công gói ' . $serviceCode);
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }

                call('Web.ActionLog.setLogDebug', array(
                    'result' => array(
                        'message' => $result['message'],
                    ),
                ));

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($result));
            } else {
                $result['errorCode'] = 2;
                call('Web.ActionLog.startTimer');
                $result['message'] = $client->getErrorMessage(); //$i18N->__('Có lỗi xảy ra trong quá trình kết nối. Quý khách vui lòng thử lại sau!');
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }

                call('Web.ActionLog.setLogDebug', array(
                    'result' => array(
                        'message' => $result['message'],
                    ),
                ));

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($result));
            }
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    public function executeGetMIList(sfWebRequest $request)
    {
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $result = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $logFields = array(
            'logType' => 'Selfcare',
            'actionType' => 'MobileInternet',
            'title' => $i18N->__('Lấy danh sách gói cước MI'),
            'service' => 'apiv2.mobileInternet.executeGetMIList',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            //lay ra danh sach cac goi da ta duoc phep dang ky
            $client = new MobileInternetClient();
            $getListAllows = $client->getListAllows($msisdn);
            $data3GNoGWClient = new data3GNoGWClient();
            $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));
            $miLimitArr = sfConfig::get('app_MI_limit');
            if (in_array($currentPakage, array_keys($miLimitArr))) {
                $isLimit = true;
            } else {
                $isLimit = false;
            }
            $miUnLimitArr = sfConfig::get('app_MI_unlimit');
            $dataLimit = array();
            $dataUnLimit = array();
            $i = 0;
            $arrFile = explode(',', sfConfig::get('app_background_file'));
            if (!empty($miLimitArr) and is_array($miLimitArr)) {
                foreach ($miLimitArr as $key => $value) {
                    if ($currentPakage == $key) {
                        continue; //bo qua cac tap lenh phia sau
                    }
                    if (!empty($getListAllows) and is_array($getListAllows)) {
                        foreach ($getListAllows as $key1 => $value1) {
                            if ($value1 == $key) {
                                $arrayData = explode("|", $value);
                                $dataLimit[$i]['service_code'] = $key;
                                $dataLimit[$i]['service_name'] = $arrayData[0];
                                $dataLimit[$i]['sub_des'] = $arrayData[1];
                                $dataLimit[$i]['sub_charges'] = $arrayData[2];
                                $dataLimit[$i]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                                $dataLimit[$i]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';
                                if ($key == 'MI10') {
                                    $dataLimit[$i]['type'] = 2;
                                } else {
                                    $dataLimit[$i]['type'] = 1;
                                }

                                $fileKey = array_rand($arrFile, 1);
                                $filename = $arrFile[$fileKey];
                                $dataLimit[$i]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';

                                $i++;
                            }
                        }
                    }
                }
            }
            $j = 0;
            if (!empty($miUnLimitArr) and is_array($miUnLimitArr)) {
                foreach ($miUnLimitArr as $key => $value) {
                    if ($currentPakage == $key) {
                        continue;
                    }
                    if (!empty($getListAllows) and is_array($getListAllows)) {
                        foreach ($getListAllows as $key1 => $value1) {
                            if ($value1 == $key) {
                                $arrayData = explode("|", $value);
                                $dataUnLimit[$j]['service_code'] = $key;
                                $dataUnLimit[$j]['service_name'] = $arrayData[0];
                                $dataUnLimit[$j]['sub_des'] = $arrayData[1];
                                $dataUnLimit[$j]['sub_charges'] = $arrayData[2];
                                $dataUnLimit[$j]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                                $dataUnLimit[$j]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';
                                if ($key == 'MI10') {
                                    $dataUnLimit[$j]['type'] = 2;
                                } else {
                                    $dataUnLimit[$j]['type'] = 1;
                                }
                                $fileKey = array_rand($arrFile, 1);
                                $filename = $arrFile[$fileKey];
                                $dataUnLimit[$j]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';
                                $j++;
                            }
                        }
                    }
                }
            }
            $dataPlusNoGWClient = new dataPlusNoGWClient();
            $response = $dataPlusNoGWClient->checkDataRemain($msisdn);
            if (isset($response->dataAcc)) {
                if (is_array($response->dataAcc)) {
                    $dataAcc = $response->dataAcc[0];
                } else {
                    $dataAcc = $response->dataAcc;
                }
            }
            $data['limit'] = $dataLimit;
            $data['unLimit'] = $dataUnLimit;
            $data['title'] = $i18N->__("Chuyển gói cước");
            $dataRemain = isset($dataAcc->remain) ? round($dataAcc->remain / 1024) : 0;
            if ($isLimit) {
                if ($dataRemain == 0) {
                    $data['des'] = $i18N->__("Quý khách đã dùng hết lưu lượng data miễn phí. Mời quý khách đăng ký gói cước để tiếp tục sử dụng Internet.");
                } else {
                    $data['des'] = $i18N->__("Quý khách còn %data%MB lưu lượng data miễn phí. Quý khách có thể tham khảo thêm một số gói cước khác dưới đây.", array('%data%' => $dataRemain));
                }
            } else {
                if ($dataRemain == 0) {
                    $data['des'] = $i18N->__("Quý khách đã dùng hết lưu lượng data miễn phí tốc độ cao. Mời quý khách đăng ký gói cước để sử dụng Internet tốc độ cao.");
                } else {
                    $data['des'] = $i18N->__("Quý khách còn %data%MB lưu lượng data tốc độ cao. Quý khách có thể tham khảo thêm một số gói cước khác dưới đây.", array('%data%' => $dataRemain));
                }
            }

            $data['titleLimit'] = $i18N->__("Gói cước theo lưu lượng sử dụng");
            $data['titleUnLimit'] = $i18N->__("Gói cước không giới hạn lưu lượng");
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = $data;
            return $this->renderText(json_encode($result));
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    public function executeGetMIListV2(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        echo "xxx";
        
        $timer = call('Web.ActionLog.startTimer');

        $webservices = array();
        $result = array();
        $logFields = array(
            'logType' => 'Selfcare',
            'actionType' => 'GetMIListV2',
            'title' => $i18N->__('Lấy danh sách gói cước MI'),
            'service' => 'apiv2.mobileInternet.executeGetMIListV2',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => '',
            'content' => '',
            'results' => json_encode($result),
        );

        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            return $this->renderText(json_encode($msisdn));
            $accountType = user()->accountType;
            //check thue bao kich hoat sau ngay 01/11/2016 thi khong hien thi chuong trinh khuyen mai nao
            $checkOff = MVPHelper::checkPromotionMsisdnOff($msisdn);

            //lay ra danh sach cac goi da ta duoc phep dang ky
            $data3GNoGWClient = new data3GNoGWClient();
            $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));

            if ($accountType == 3) {    // dcom
                $MIPackages = call('App.Viettel.InternetPackage.selectAll', array(
                    'type' => 'InternetPackage.Dcom',
                    'itemsPerPage' => 100000,
                    'orderBy' => 'code ASC',
                    'filters' => array(
                        'status' => '1',
                    ),
                ));
            } else {    // mob

                $MIPackages = call('App.Viettel.InternetPackage.selectAll', array(
                    'type' => 'InternetPackage.MobileInternet',
                    'itemsPerPage' => 100000,
                    'orderBy' => 'code ASC',
                    'filters' => array(
                        'status' => '1',
                    ),
                ));
            }

            $arrMass = array();
            $arr191 = array();
            //check neu dang su dung di dong
            if ($accountType == 1) {

                $listAll = $request->getParameter('list_all');
                $listDataUssd = sfConfig::get('app_list_data_ussd');
                $arrReturn = null;
                $message = null;
                // data
                $client = new WsDataClient();
                $arrListReg = array();
                $listDataDb = array();
                $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
                    'type' => 'InternetPackage.MobileInternet'
                ));
                if (!empty($listDataDbMi)) {
                    foreach ($listDataDbMi as $key => $itemDB) {
                        $listDataDb[strtolower($key)] = $itemDB;
                    }
                }
                $listDataDbAddon = call('App.Viettel.InternetPackage.getListAllPackage', array(
                    'type' => 'InternetPackage.Addon'
                ));
                //lưu danh sách mã các gói addon
                $arrCodeAddon = array();
                $allAddonDb = array();
                if (!empty($listDataDbAddon)) {
                    foreach ($listDataDbAddon as $key => $itemDB) {
                        if ($itemDB['status'] == 1) {
                            $arrCodeAddon[] = $itemDB['code'];
                        }
                        $allAddonDb[strtolower($key)] = $itemDB;
                    }
                }
                $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                $rtPackDataNew = $rtPackDataNew['arrReturn'];
                //lấy ra các gói 4G
                $arrData4G = array();
                if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                    foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                        //lấy ra gói 4G
                        if ($a['is4G'] == 1) {
                            $arrData4G[] = $a;
                        }
                    }
                }
                //bỏ lọc trùng các gói addon và loại bỏ các gói 4G
                if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                    foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                        if ($a['is4G'] == 1) {
                            unset($rtPackDataNew['data']['list'][0]['list_data'][$k]);
                        }
                    }
                }
                $lstArr3G = $rtPackDataNew['data']['list'];

                $arrReturnData[0] = $arrData4G;
                $arr3G = null;
                if (count($lstArr3G)) {
                    foreach ($lstArr3G as $val1) {
                        if (isset($val1['list_data']) && count($val1['list_data'])) {
                            foreach ($val1['list_data'] as $val2) {
                                $arr3G[] = $val2;
                            }
                        }
                    }
                }

                $arrAll = array_merge($arrData4G, $arr3G);
            }
            //neu la thue bao bi off Km thi khong hien thi
            if ($checkOff) {
                $arr191 = array();
            }

            $MIPackages = !empty($MIPackages['items']) ? $MIPackages['items'] : array();
            $dataLimit = array();
            $dataUnLimit = array();
            $i = $j = 0;
            $isLimit = false;
            $arrFile = explode(',', sfConfig::get('app_background_file'));
            $miUnLimitArr = array();

            $listIgnoreResponse = Data("Decl")->useIndex('code')->select(array('code' => 'config_pack_default'));

            if($listIgnoreResponse && $listIgnoreResponse["value"]) {
                $listIgnore = explode(",",$listIgnoreResponse['value']);
            } else {
                $listIgnore = array("MIMD","I0","I.0","GP_STU","GP_SCL","MIF","MIMD_HSSV","MI0","MIMDX","I0X","MIFX","MITS","MI0X","DC0","D.0");
            }

            $dataws = new DataWS();
            $packUsing = $dataws->getRegistedVasInfo($msisdn);

            $dataUsing = $packUsing ? $packUsing['data'] : array();

            $subInfo = new VtpGetSubInfoMob();
            $info = $subInfo->getSubInfo($msisdn);

            $serviceType = PRE_PAID;
            if ($info) {
                $serviceType = $info['SERVICE_TYPE'] == 'PRE_PAID' ? PRE_PAID : POST_PAID;
            }

            foreach ($arrAll as $item) {
                if (!empty($item['pack_code'])) {
                    $itemData = array();
                    $itemData['id'] = $item['id'];
                    $itemData['service_code'] = $item['pack_code'];
                    $itemData['service_name'] = !empty($item['pack_code']) ? $item['pack_code'] : "";
                    $itemData['sub_des'] = !empty($item['display']) ? trim(strip_tags(html_entity_decode($item['display'], ENT_HTML401, 'UTF-8'))) : '';
                    $itemData['sub_charges'] = !empty($item['price']) ? $item['price'] : '';
                    $itemData['over_data'] = '0';
                    $itemData['image'] = !empty($item['image']) ? href($item['image']) : '';
                    $itemData['type'] = !empty($item['packageType']) ? $item['packageType'] : 1;
                    $fileKey = array_rand($arrFile, 1);
                    $filename = $arrFile[$fileKey];
                    $itemData['background'] = portal()->mediaDomain . 'media/uploads/background/' . $filename . '.png';

                    $isChangeDataReserve = $dataws->checkRegDataNeedConfirm($dataUsing[0], $item['pack_code'], $serviceType);
                    $confirm_reg_msg = !empty($itemData['confirm']) ? $itemData['confirm'] : $i18N->__('Bạn muốn đăng ký gói cước '.$itemData['pack_code'].'?');
                    $price = !empty($itemData['sub_charges']) ? (int)str_replace(".", "", $itemData['sub_charges']) : 0;
                    $price = number_format($price, 0 , ",", ".");
                    if ((!$dataUsing || sizeof($dataUsing) == 0) || in_array($dataUsing[0], $listIgnore)) {
                        $itemData['confirm_reg'] = $confirm_reg_msg;
                    } else {
                        if ($isChangeDataReserve == "true") {
                            $itemData['confirm_reg'] = $i18N->__("Quý khách đang yêu cầu đăng ký gói data ".$item['pack_code']." với giá ".$price." đồng. Lưu ý: Lưu lượng data còn lại của gói ".$dataUsing[0]." sẽ được bảo lưu");
                        } else {
                            $itemData['confirm_reg'] = $i18N->__("Quý khách đang yêu cầu đăng ký gói data ".$item['pack_code']." với giá ".$price." đồng. Lưu ý: Lưu lượng data còn lại của gói ".$dataUsing[0]." sẽ KHÔNG được bảo lưu");
                        }
                    }
                    $dataLimit[] = $itemData;
                }
            }
            $dataPlusNoGWClient = new dataPlusNoGWClient();
            $response = $dataPlusNoGWClient->checkDataRemain($msisdn);
            if (is_array($response->dataAcc)) {
                $dataAcc = $response->dataAcc[0];
            } else {
                $dataAcc = $response->dataAcc;
            }
            $data['limit'] = $dataLimit;
            $data['unLimit'] = $dataUnLimit;
            $data['title'] = $i18N->__("Chuyển gói cước");
            $data['currentPackage'] = $currentPakage;
            //lay danh sach cac goi addon
            //copy moi
            //$addonsArray = sfConfig::get('app_ADDON');
            $addonsArray = call('App.Viettel.InternetPackage.selectAll', array(
                'type' => 'InternetPackage.Addon',
                'itemsPerPage' => 100000,
                'orderBy' => 'code ASC',
                'filters' => array(
                    'status' => '1',
                ),
            ));
            $arrAddonUsed = array();
            //het
            $client3G = new data3GNoGWClient();
            $reponseAddOn = $client3G->checkDataAddon($msisdn);
            if ($reponseAddOn) {
                $reponseAddOn = rtrim($reponseAddOn, "|");
                $data['addOnPackage'] = $reponseAddOn;
                $arrAddonUsed = explode("|", $reponseAddOn);
            } else {
                $data['addOnPackage'] = array();
            }
            $dataRemain = round($dataAcc->remain / 1024);
            //copy moi
            $k = 0;
            $ka = 0;
            $listAddOnUsed = array();
            $dataAddon = array();
            foreach ($addonsArray['items'] as $key => $value) {
                if (empty($value['code'])) {
                    continue;
                }
                foreach ($arrAddonUsed as $va) {
                    if (strtolower($value['code']) == strtolower($va)) {
                        $listAddOnUsed[$ka]['service_code'] = $value['code'];
                        $listAddOnUsed[$ka]['service_name'] = !empty($value['title']) ? $value['title'] : $value['code'];
                        $listAddOnUsed[$ka]['sub_des'] = !empty($value['description']) ? trim(strip_tags(html_entity_decode($value['description'], ENT_HTML401, 'UTF-8'))) : '';
                        $listAddOnUsed[$ka]['sub_charges'] = !empty($value['price']) ? $value['price'] : '';
                        $listAddOnUsed[$ka]['over_data'] = !empty($value['highSpeed']) ? ApiHelper::convertDataToMB($value['highSpeed']) : '';
                        $listAddOnUsed[$ka]['image'] = portal()->mediaDomain . 'media/uploads/midata/' . $value['code'] . '.png';
                        $fileKey = array_rand($arrFile, 1);
                        $filename = $arrFile[$fileKey];
                        $listAddOnUsed[$ka]['background'] = portal()->mediaDomain . 'media/uploads/background/' . $filename . '.png';
                        $ka++;
                        continue;
                    }
                }

                $dataAddon[$k]['service_code'] = $value['code'];
                $dataAddon[$k]['service_name'] = !empty($value['title']) ? $value['title'] : $value['code'];
                $dataAddon[$k]['sub_des'] = !empty($value['description']) ? trim(strip_tags(html_entity_decode($value['description'], ENT_HTML401, 'UTF-8'))) : '';
                $dataAddon[$k]['sub_charges'] = !empty($value['price']) ? $value['price'] : '';
                $dataAddon[$k]['over_data'] = !empty($value['highSpeed']) ? ApiHelper::convertDataToMB($value['highSpeed']) : '';
                $dataAddon[$k]['image'] = portal()->mediaDomain . 'media/uploads/midata/' . $value['code'] . '.png';

                $fileKey = array_rand($arrFile, 1);
                $filename = $arrFile[$fileKey];
                $dataAddon[$k]['background'] = portal()->mediaDomain . 'media/uploads/background/' . $filename . '.png';
                $k++;

            }
            $data['addon'] = $dataAddon;
            if (count($listAddOnUsed)) {
                $data['addOnPackage'] = $listAddOnUsed;
            } else {
                $data['addOnPackage'] = array();
            }
            //het
            if ($isLimit) {
                if ($dataRemain == 0) {
                    $data['des'] = $i18N->__("Quý khách đã dùng hết lưu lượng data miễn phí. Mời quý khách đăng ký gói cước để tiếp tục sử dụng Internet.");
                } else {
                    $data['des'] = $i18N->__("Quý khách còn %data%MB lưu lượng data miễn phí. Quý khách có thể tham khảo thêm một số gói cước khác dưới đây.", array('%data%' => $dataRemain));
                }
            } else {
                if ($dataRemain == 0) {
                    $data['des'] = $i18N->__("Quý khách đã dùng hết lưu lượng data miễn phí tốc độ cao. Mời quý khách đăng ký gói cước để sử dụng Internet tốc độ cao.");
                } else {
                    $data['des'] = $i18N->__("Quý khách còn %data%MB lưu lượng data tốc độ cao. Quý khách có thể tham khảo thêm một số gói cước khác dưới đây.", array('%data%' => $dataRemain));
                }
            }

            $data['titleLimit'] = $i18N->__("Gói cước theo lưu lượng sử dụng");
            $data['titleUnLimit'] = $i18N->__("Gói cước không giới hạn lưu lượng");
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = $data;
            return $this->renderText(json_encode($result));
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    public function executeGetAllDataMass(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $webservices = array();
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'GetMIListNoneToken',
            'title' => $i18N->__('Lấy danh sách gói cước MI đại trà'),
            'service' => 'myviettel.mobileInternet.executeGetMIListNoneToken',
            'creatorId' => '',
            'objectId' => '',
            'objectTitle' => '',
            'webservices' => json_encode($webservices),
            'inputs' => '',
            'content' => '',
            'results' => json_encode($result),
        );

        if (!$request->isMethod('POST')) {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }

        //lay ra danh sach cac goi data dai tra
        $arrProductMi = array();
        $arrProductDcom = array();
        $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.MobileInternet', 'onlyMass' => true
        ));
        $listDataDbDcom = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Dcom', 'onlyMass' => true
        ));

        foreach ($listDataDbMi as $item) {
            $arrProductMi[] = self::generateDataMass($item);
        }
        foreach ($listDataDbDcom as $item) {
            $arrProductDcom[] = self::generateDataMass($item);
        }

        usort($arrProductMi, function ($a, $b) {
            return $a['price'] > $b['price'];
        });
        usort($arrProductDcom, function ($a, $b) {
            return $a['price'] > $b['price'];
        });

        $arrReturn = array(
            array(
                'type' => 'data_new',
                'name' => $i18N->__('Gói cước 3G/4G'),
                'list' => $arrProductMi
            ),
            array(
                'type' => 'dcom',
                'name' => $i18N->__('Gói cước Dcom'),
                'list' => $arrProductDcom
            )
        );
        $result['errorCode'] = 0;
        $result['message'] = $i18N->__('Thành công');
        if (!empty($arrReturn)) {
            $result['data'] = $arrReturn;
        } else {
            $result['data'] = array();
        }
        return $this->renderText(json_encode($result));

    }

    public static function generateDataMass($pacDataDb, $product = false, $isReg = 0, $listPackAllowRegisterAgain = array(), $extraConfirmRegMsg="", $oldPakageName="")
    {
        $i18n = sfContext::getInstance()->getI18n();
        $tags = (!empty($product->infoStr) && !is_object($product->infoStr)) ? self::replaceStringTags($product->infoStr) : '';
        $newPriceDisplay = !empty($pacDataDb['price']) ? (int)str_replace(".", "", $pacDataDb['price']) : 0;
        if ($pacDataDb) {
            $registerAgain = in_array($pacDataDb['code'], $listPackAllowRegisterAgain) ? 1 : 0;
            //when extra confirm message has been received, that mean we need to build a new message in new way. Else, we keep the old behaviour
            if($extraConfirmRegMsg === "") {
                //old behaviour
                $confirm_reg_msg = !empty($pacDataDb['confirm']) ? $pacDataDb['confirm'] : $i18n->__('Bạn muốn đăng ký gói cước '.$pacDataDb['code'].'?');
            } else {
                //build new message
                $confirm_reg_msg = $i18n->__("Quý khách đang yêu cầu đăng ký gói data ".$pacDataDb['code']." với giá ".$newPriceDisplay." đồng. Lưu ý: Lưu lượng data còn lại của gói ".$oldPakageName." sẽ ".$extraConfirmRegMsg);
            }
            $arrPackage = array(
                'id' => $pacDataDb['id'],
                'pack_code' => $pacDataDb['code'],
                'display' => $pacDataDb['brief'],
                'type_xntvbh' => 2,
                'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : null,
                'is_reg' => $isReg,
                'is_098' => !empty($pacDataDb['isDisplay098']) ? 1 : 0,
                'is_bang_ma_098' => 0,
                'type' => 1,
                'label_reg' => $i18n->__('Đăng ký'),
                'label_unreg' => 'Hủy',
                'confirm_reg' => !empty($pacDataDb['confirm']) ? $pacDataDb['confirm'] : $i18n->__('Bạn muốn đăng ký gói cước?'),
                'confirm_unreg' => !empty($pacDataDb['confirmCancel']) ? $pacDataDb['confirmCancel'] : $i18n->__('Bạn muốn hủy gói cước?'),
                'is4G' => !empty($pacDataDb['is4G']) ? $pacDataDb['is4G'] : 0,
                # nhatdv1 xhhbh
                'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                'socialSellType' => 2,
                // them bundle
                'tags' => $tags,
                'price' => !empty($pacDataDb['price']) ? (int)str_replace(".", "", $pacDataDb['price']) : 0,
                'image' => (isset($pacDataDb['image']) && $pacDataDb['image'] != '') ? VTPHelper::getImagePath($pacDataDb['image']) : null,
                'register_again' => $registerAgain

            );
            return $arrPackage;
        }
        return false;
    }

    public function executeGetMIListV2_1(sfWebRequest $request)
    {

        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $result = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $logFields = array(
            'logType' => 'Selfcare',
            'actionType' => 'MobileInternet',
            'title' => $i18N->__('Lấy danh sách gói cước MI'),
            'service' => 'apiv2.mobileInternet.executeGetMIListV2',
            'objectId' => '',
            'objectTitle' => '',
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            //lay ra danh sach cac goi da ta duoc phep dang ky
            $client = new MobileInternetClient();
            $getListAllows = $client->getListAllows($msisdn);
            $data3GNoGWClient = new data3GNoGWClient();
            $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));
            $miLimitArr = sfConfig::get('app_MI_limit');
            $miUnLimitArr = sfConfig::get('app_MI_unlimit');
            if (in_array($currentPakage, array_keys($miLimitArr))) {
                $isLimit = true;
            } else {
                $isLimit = false;
            }
            $dataLimit = array();
            $dataUnLimit = array();
            $i = $j = 0;
            $arrFile = explode(',', sfConfig::get('app_background_file'));
            foreach ($miLimitArr as $key => $value) {
                if ($currentPakage == $key) {
                    continue;
                }
                if (is_array($getListAllows)) {
                    foreach ($getListAllows as $key1 => $value1) {
                        if ($value1 == $key) {
                            $arrayData = explode("|", $value);
                            $dataLimit[$i]['service_code'] = $key;
                            $dataLimit[$i]['service_name'] = $arrayData[0];
                            $dataLimit[$i]['sub_des'] = $arrayData[1];
                            $dataLimit[$i]['sub_charges'] = $arrayData[2];
                            $dataLimit[$i]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                            $dataLimit[$i]['image'] = 'http://' . portal()->realDomain . 'media/uploads/midata/' . $key . '.png';
                            if ($key == 'MI10') {
                                $dataLimit[$i]['type'] = 2;
                            } else {
                                $dataLimit[$i]['type'] = 1;
                            }

                            $fileKey = array_rand($arrFile, 1);
                            $filename = $arrFile[$fileKey];
                            $dataLimit[$i]['background'] = 'http://' . portal()->realDomain . 'media/uploads/background/' . $filename . '.png';

                            $i++;
                        }
                    }
                }
            }
            foreach ($miUnLimitArr as $key => $value) {
                if ($currentPakage == $key) {
                    continue;
                }
                if ($key == 'MIMAX0QT') {
                    continue;
                }
                if (is_array($getListAllows)) {
                    foreach ($getListAllows as $key1 => $value1) {
                        if ($value1 == $key) {
                            $arrayData = explode("|", $value);
                            $dataUnLimit[$j]['service_code'] = $key;
                            $dataUnLimit[$j]['service_name'] = $arrayData[0];
                            $dataUnLimit[$j]['sub_des'] = $arrayData[1];
                            $dataUnLimit[$j]['sub_charges'] = $arrayData[2];
                            $dataUnLimit[$j]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                            $dataUnLimit[$j]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';
                            if ($key == 'MI10') {
                                $dataUnLimit[$j]['type'] = 2;
                            } else {
                                $dataUnLimit[$j]['type'] = 1;
                            }
                            $fileKey = array_rand($arrFile, 1);
                            $filename = $arrFile[$fileKey];
                            $dataUnLimit[$j]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';
                            $j++;
                        }
                    }
                }
            }
            $dataPlusNoGWClient = new dataPlusNoGWClient();
            $response = $dataPlusNoGWClient->checkDataRemain($msisdn);
            if (is_object($response) and is_array($response->dataAcc)) {
                $dataAcc = $response->dataAcc[0];
            } else {
                $dataAcc = $response->dataAcc;
            }

            $data['limit'] = $dataLimit;
            $data['unLimit'] = $dataUnLimit;
            $data['title'] = $i18N->__("Chuyển gói cước");
            $data['currentPackage'] = $currentPakage;

            //lay danh sach cac goi addon
            //copy moi
            $addonsArray = sfConfig::get('app_ADDON');
            $arrAddonUsed = array();
            //het
            $client3G = new data3GNoGWClient();
            $reponseAddOn = $client3G->checkDataAddon($msisdn);
            if ($reponseAddOn) {
                $reponseAddOn = rtrim($reponseAddOn, "|");
                $data['addOnPackage'] = $reponseAddOn;
                $arrAddonUsed = explode("|", $reponseAddOn);
            } else {
                $data['addOnPackage'] = array();
            }
            $dataRemain = round($dataAcc->remain / 1024);
            //copy moi
            $k = 0;
            $ka = 0;
            $listAddOnUsed = array();
            foreach ($addonsArray as $key => $value) {
                foreach ($arrAddonUsed as $va) {
                    if ($key == $va) {
                        $arrayData = explode("|", $value);
                        $listAddOnUsed[$ka]['service_code'] = $key;
                        $listAddOnUsed[$ka]['service_name'] = $arrayData[0];
                        $listAddOnUsed[$ka]['sub_des'] = $arrayData[1];
                        $listAddOnUsed[$ka]['sub_charges'] = $arrayData[2];
                        $listAddOnUsed[$ka]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                        $listAddOnUsed[$ka]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';
                        $fileKey = array_rand($arrFile, 1);
                        $filename = $arrFile[$fileKey];
                        $listAddOnUsed[$ka]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';
                        $ka++;
                        continue;
                    }
                }
                //Kiem tra neu la goi FB Thang FB30 (Chi ap dung cho MI unlimited)
                if ($key == 'FB30') {
                    if (in_array($currentPakage, array_keys($miUnLimitArr))) {
                        $arrayData = explode("|", $value);
                        $dataAddon[$k]['service_code'] = $key;
                        $dataAddon[$k]['service_name'] = $arrayData[0];
                        $dataAddon[$k]['sub_des'] = $arrayData[1];
                        $dataAddon[$k]['sub_charges'] = $arrayData[2];
                        $dataAddon[$k]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                        $dataAddon[$k]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';

                        $fileKey = array_rand($arrFile, 1);
                        $filename = $arrFile[$fileKey];
                        $dataAddon[$k]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';
                        $k++;
                        continue;
                    } else {
                        continue;
                    }
                }
                //Kiem tra doi voi goi doc bao DB1 va DB30
                if ($key == 'DB1' || $key == 'DB30') {
                    if ($currentPakage == 'MIMD' || $currentPakage == 'MIMAX0' || $currentPakage == false) {
                        $arrayData = explode("|", $value);
                        $dataAddon[$k]['service_code'] = $key;
                        $dataAddon[$k]['service_name'] = $arrayData[0];
                        $dataAddon[$k]['sub_des'] = $arrayData[1];
                        $dataAddon[$k]['sub_charges'] = $arrayData[2];
                        $dataAddon[$k]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                        $dataAddon[$k]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';

                        $fileKey = array_rand($arrFile, 1);
                        $filename = $arrFile[$fileKey];
                        $dataAddon[$k]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';
                        $k++;
                        continue;
                    } else {
                        continue;
                    }
                }

                $arrayData = explode("|", $value);
                $dataAddon[$k]['service_code'] = $key;
                $dataAddon[$k]['service_name'] = $arrayData[0];
                $dataAddon[$k]['sub_des'] = $arrayData[1];
                $dataAddon[$k]['sub_charges'] = $arrayData[2];
                $dataAddon[$k]['over_data'] = ApiHelper::convertDataToMB($arrayData[3]);
                $dataAddon[$k]['image'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/midata/' . $key . '.png';

                $fileKey = array_rand($arrFile, 1);
                $filename = $arrFile[$fileKey];
                $dataAddon[$k]['background'] = sfConfig::get('app_domain', 'http://vietteltelecom.vn') . '/uploads/background/' . $filename . '.png';
                $k++;

            }
            $data['addon'] = $dataAddon;
            if (count($listAddOnUsed)) {
                $data['addOnPackage'] = $listAddOnUsed;
            } else {
                $data['addOnPackage'] = array();
            }
            //het
            if ($isLimit) {
                if ($dataRemain == 0) {
                    $data['des'] = $i18N->__("Quý khách đã dùng hết lưu lượng data miễn phí. Mời quý khách đăng ký gói cước để tiếp tục sử dụng Internet.");
                } else {
                    $data['des'] = $i18N->__("Quý khách còn %data%MB lưu lượng data miễn phí. Quý khách có thể tham khảo thêm một số gói cước khác dưới đây.", array('%data%' => $dataRemain));
                }
            } else {
                if ($dataRemain == 0) {
                    $data['des'] = $i18N->__("Quý khách đã dùng hết lưu lượng data miễn phí tốc độ cao. Mời quý khách đăng ký gói cước để sử dụng Internet tốc độ cao.");
                } else {
                    $data['des'] = $i18N->__("Quý khách còn %data%MB lưu lượng data tốc độ cao. Quý khách có thể tham khảo thêm một số gói cước khác dưới đây.", array('%data%' => $dataRemain));
                }
            }

            $data['titleLimit'] = $i18N->__("Gói cước theo lưu lượng sử dụng");
            $data['titleUnLimit'] = $i18N->__("Gói cước không giới hạn lưu lượng");
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = $data;
            return $this->renderText(json_encode($result));
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    // huync2: huy goi MI
    public function executeUnRegisterMI(sfWebRequest $request)
    {
        call('Web.ActionLog.startTimerDebug');
        $timer = call('Web.ActionLog.startTimer');
        $i18n = sfContext::getInstance()->getI18N();
        $webservices = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'UnregisterData',
            'title' => $i18n->__('Hủy đăng ký gói cước MI'),
            'service' => 'apiv2.mobileInternet.executeUnRegisterMI',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );

        if (!$request->isMethod('POST')) {
            $arrReturn = array(
                'errorCode' => '1',
                'message' => $i18n->__('Phương thức không hợp lệ'),
            );
            return $this->renderText(json_encode($arrReturn));
        }
        $this->setLayout(false);
        $msisdn = user()->id;
        $package = $request->getParameter('service_code');

        if (!$package) {

            call('Web.ActionLog.setLogDebug', array(
                'result' => array(
                    'message' => $i18n->__('Mã gói cước không được để trống')
                ),
            ));

            $logFields['errCode'] = '1';
            $logFields['message'] = $i18n->__('Mã gói cước không được để trống');
            if (!empty($arrReturn)) {
                $logFields['results'] = json_encode($arrReturn);
            }

            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return new Response(CommonErrorCode::PARAM_INVALID, $i18n->__('Mã gói cước không được để trống'));
        }

        # huync2: kiem tra goi Data trong DB truoc khi huy
        $checkData = self::checkPackage(trim($package));

        call('Web.ActionLog.setLogDebug', array(
            'result' => array(
                'message' => $i18n->__('Kiểm tra gói cước trong DB'),
                'result' => $checkData
            ),
        ));

        if ($checkData == false) {
            sfContext::getInstance()->getLogger()->log('checkUnRegData apiv2.mobileInternet.executeUnRegisterMI goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18n->__('Gói cước không hợp lệ.')
            );

            call('Web.ActionLog.setLogDebug', array(
                'result' => array(
                    'message' => $i18n->__('Gói cước không hợp lệ. do ko đc khai trong DB'),
                    'result' => $checkData,
                ),
            ));
            if (!empty($arrReturn)) {
                $logFields['results'] = json_encode($arrReturn);
            }

            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($arrReturn));
        }

        //nguyetNT32: chặn 4 gói 0 mất tiền
        $arrPackege = array('MIMIN', 'MI0', 'MIMAX0', 'DC0');
        if (in_array(strtoupper($package), $arrPackege)) {
            $result['errorCode'] = 3;
            $result['message'] = $i18n->__('Gói cước này hiện không được hủy trên My Viettel, vui lòng thử lại sau!'); //Truyền thiếu tham số service_code
            $result['data'] = null;

            call('Web.ActionLog.setLogDebug', array(
                'result' => array(
                    'message' => $result['message'],
                    'message2' => $i18n->__('chặn 4 gói 0 mất tiền'),
                    'result' => $checkData,
                ),
            ));

            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }

            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }

        $client = new MobileInternetClient();
        $response = $client->unRegisterMI($msisdn, $package);

        if ($response) {
            $arrReturn = array(
                'errorCode' => '0',
                'message' => $i18n->__('Hủy gói cước thành công.'),
            );
        } else {
            $arrReturn = array(
                'errorCode' => '9',
                'message' => $i18n->__('Hủy gói cước không thành công'),
            );
        }

        call('Web.ActionLog.setLogDebug', array(
            'result' => array(
                'message' => $arrReturn['errorCode'],
            ),
        ));

        if (!empty($arrReturn)) {
            $logFields['results'] = json_encode($arrReturn);
        }
        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        return $this->renderText(json_encode($arrReturn));
    }

    public function executeBuyData(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        call('Web.ActionLog.startTimerDebug');
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'RegisterData',
            'title' => $i18N->__('Mua data'),
            'service' => 'apiv2.mobileInternet.executeBuyData',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            $package = $request->getParameter('package_name', null);

            $logFields['inputs'] = json_encode($request);
            if ($package == null) {
                $result['errorCode'] = 3;
                $result['message'] = $i18N->__('Truyền thiếu tham số package_name');
                $result['data'] = null;

                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
            $checkData = self::checkPackage($package, 3);
            if ($checkData == false) {
                sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeBuyData: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                $arrReturn = ApiHelper::formatResponse(
                    ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                );

                if (!empty($arrReturn)) {
                    $logFields['results'] = json_encode($arrReturn);
                }

                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($arrReturn));
            }
            $client = new dataPlusClient();
            $response = $client->DataPlusBuy($msisdn, $package);
            if ($response) {
                $result['errorCode'] = 0;
                $result['message'] = $i18N->__('Mua thêm data thành công');
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }
                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($result));
            } else {
                $result['errorCode'] = 2;
                $result['message'] = $client->getErrorMessage(); //$i18N->__('Có lỗi trong quá trình thực hiện! Quý khách vui lòng thử lại sau!');
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }
                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    public function executeGetDataList(sfWebRequest $request)
    {
        $i18n = $this->getContext()->getI18N();
        $st = VTPHelper::getMilliTime();
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Selfcare',
            'actionType' => 'MobileInternet',
            'title' => $i18n->__('Lấy danh sách gói dữ liệu'),
            'service' => 'apiv2.mobileInternet.executeGetDataList',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );

        if ($request->isMethod('POST')) {
            $i = 0;
            $data = array();
            $msisdn = user()->msisdn;

            {
                $st2 = VTPHelper::getMilliTime();
                $client = new Offer();
                $listProduct = $client->getUssdMenu($msisdn, 'INTERNET,COMBO,HOT,DATAPLUS');
                VTPHelper::logTime('getDataList_ussd|', user()->id . '|total time', $st2);
                foreach ($listProduct as $product) {
                    $pkcode = $product->name;
                    if ($product->productType == 3) {
                        $listPackage[] = strtolower($pkcode);
                    }
                }

                // lay thong tin goi data tu DB
                $listDataDbPlus = call('App.Viettel.InternetPackage.getAllPackageBuyDataFront');
                $listDataDb = array();
                foreach ($listDataDbPlus as $key => $itemDB) {
                    if (in_array(strtolower($key), $listPackage)) {
                        $arr = array();
                        $arr['package_name'] = $key;
                        $arr['package_des'] = $itemDB['description'];
                        $arr['package_price'] = $itemDB['price'];
                        $arr['package_data'] = ApiHelper::convertDataToMB($itemDB['highSpeed']);
                        $arr['package_cycle'] = $itemDB['cycle'];
                        $arr['ctt'] = (isset($itemDB['s5']) && $itemDB['s5']) ? 0 : 1;
                        $listDataDb[] = $arr;
                    }
                }

                usort($listDataDb, function ($a, $b) {
                    return $a['package_data'] - $b['package_data'];
                });
                /* The next line is used for debugging, comment or delete it after testing */
                $result['errorCode'] = 0;
                $result['message'] = $i18n->__('Thành công');
                $result['data'] = $listDataDb;
            }

        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18n->__('Sai phương thức HTTP');
            $result['data'] = [];
        }
        if (!empty($result)) {
            $logFields['results'] = ($result['errorCode' == 0]) ? 'SUCCESS' : 'FAIL';
        }
        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        VTPHelper::logTime('getDataList|', user()->id . '|total time', $st);
        return $this->renderText(json_encode($result));
    }

    public function getPackData($listDataUssd, $msisdn, $listDataDb, $arrListReg)
    {
        $webservices = array();
        $client = new WsDataClient();
        $result = $client->getDataUSSD($msisdn);
        $arrPackage = array();
        if ($result) {
            //lay danh sach cac ma dich vu tren 098
            $resultCode098 = call('App.Viettel.Package098.getPackageDisplay');
            $arrCode098 = array();
            if (!empty($resultCode098)) {
                foreach ($resultCode098 as $item098) {
                    $arrCode098[] = $item098['title'];
                }
            }
            $codeOrig = VTPHelper::getOriginalBccsGW("<return>" . $result . "</return>", "<return>", "</return>");
            $regType = $listDataUssd['data']['reg_type'];

            if (!is_array($codeOrig->package)) {
                $isReg = ApiHelper::checkRegData(strtoupper($codeOrig->package->name), $arrListReg) == true ? 1 : 0;

                if (ApiHelper::checkPackageDB(strtolower($codeOrig->package->name), array_keys($listDataDb))) {

                    $pacDataDb = $listDataDb[strtolower($codeOrig->package->name)];
                    $arrPackage[] = array(
                        'id' => $pacDataDb['id'],
                        'pack_code' => $codeOrig->package->name,
                        'type_xntvbh' => 2,
                        'display' => $pacDataDb['brief'] != '' ? $pacDataDb['brief'] : $codeOrig->package->display,
                        'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : $codeOrig->package->detail,
                        'is_reg' => $isReg,
                        'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                        'is_bang_ma_098' => in_array($codeOrig->package->name, $arrCode098) ? '1' : 0,
                        'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                        'label_reg' => $regType['label_reg'],
                        'label_unreg' => ucwords($regType['label_unreg']),
                        'confirm_reg' => $pacDataDb['confirm'] != '' ? $pacDataDb['confirm'] : str_replace('[package]', $codeOrig->package->display, $regType['confirm_reg']),
                        'confirm_unreg' => $pacDataDb['confirmCancel'] != '' ? $pacDataDb['confirmCancel'] : str_replace('[package]', $codeOrig->package->display, $regType['confirm_unreg']),
                        # nhatdv1 xhhbh
                        'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                        'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                        'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                        'socialSellType' => self::TYPE_GET_PROMOTION_DATA_USSD
                    );
                } else {
                    /*
                    $arrPackage[] = array(
                        'pack_code' => $codeOrig->package->name,
                        'type_xntvbh' => 2,
                        'display' => $codeOrig->package->display,
                        'detail' => $codeOrig->package->detail,
                        'is_reg' => $isReg,
                        'is_098' => 0,
                        'is_bang_ma_098' => in_array($codeOrig->package->name, $arrCode098) ? '1' : 0,
                        'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                        'label_reg' => $regType['label_reg'],
                        'label_unreg' => ucwords($regType['label_unreg']),
                        'confirm_reg' => str_replace('[package]', $codeOrig->package->display, $regType['confirm_reg']),
                        'confirm_unreg' => str_replace('[package]', $codeOrig->package->display, $regType['confirm_unreg']),
                    );
                    */
                }
            } else {
                foreach ($codeOrig->package as $package) {
                    $isReg = ApiHelper::checkRegData(strtoupper($package->name), $arrListReg) == true ? 1 : 0;
                    if (ApiHelper::checkPackageDB(strtolower($package->name), array_keys($listDataDb))) {
                        $pacDataDb = $listDataDb[strtolower($package->name)];
                        $arrPackage[] = array(
                            'id' => $pacDataDb['id'],
                            'pack_code' => $package->name,
                            'type_xntvbh' => 2,
                            'display' => $pacDataDb['brief'] != '' ? $pacDataDb['brief'] : $package->display,
                            'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : $package->detail,
                            'is_reg' => $isReg,
                            'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                            'is_bang_ma_098' => in_array($package->name, $arrCode098) ? '1' : 0,
                            'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                            'label_reg' => $regType['label_reg'],
                            'label_unreg' => ucwords($regType['label_unreg']),
                            'confirm_reg' => $pacDataDb['confirm'] != '' ? $pacDataDb['confirm'] : str_replace('[package]', $package->display, $regType['confirm_reg']),
                            'confirm_unreg' => $pacDataDb['confirmCancel'] != '' ? $pacDataDb['confirmCancel'] : str_replace('[package]', $package->display, $regType['confirm_unreg']),
                            # nhatdv1 xhhbh
                            'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                            'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                            'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                            'socialSellType' => self::TYPE_GET_PROMOTION_DATA_USSD
                        );
                    } else {
                        continue;
//                        $arrPackage[] = array(
//                            'pack_code' => $package->name,
//                            'type_xntvbh' => 2,
//                            'display' => $package->display,
//                            'detail' => $package->detail,
//                            'is_reg' => $isReg,
//                            'is_098' => 0,
//                            'is_bang_ma_098' => in_array($package->name, $arrCode098) ? '1' : 0,
//                            'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
//                            'label_reg' => $regType['label_reg'],
//                            'label_unreg' => ucwords($regType['label_unreg']),
//                            'confirm_reg' => str_replace('[package]', $package->display, $regType['confirm_reg']),
//                            'confirm_unreg' => str_replace('[package]', $package->display, $regType['confirm_unreg']),
//                        );
                    }
                }
            }
        }
        $return = array(
            'arrPackage' => !empty($arrPackage) ? $arrPackage : [],
            'webservices' => $webservices
        );
        return $return;
    }

    public function returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg)
    {
        $i18n = $this->getContext()->getI18N();
        $arrListDataConfig = $listDataUssd['data']['list'];
        $webservices = array();
        $arrTypeConfig = $listDataUssd['data'];
        $regType = $listDataUssd['data']['reg_type'];
        //lay danh sach cac ma dich vu tren 098
        $resultCode098 = call('App.Viettel.Package098.getPackageDisplay');
        $arrCode098 = array();
        if (!empty($resultCode098)) {
            foreach ($resultCode098 as $item098) {
                $arrCode098[] = $item098['title'];
            }
        }
        // data_day
        $arrDataDay = array();
        if ($arrListDataConfig['data_day']['show_list'] == '1') {
            foreach ($arrListDataConfig['data_day']['list_data'] as $item) {
                $isReg = ApiHelper::checkRegData(strtoupper($item['name']), $arrListReg) == true ? 1 : 0;


                if (ApiHelper::checkPackageDB(strtolower($item['name']), array_keys($listDataDb))) {
                    $pacDataDb = $listDataDb[strtolower($item['name'])];

                    $arrDataDay[] = array(
                        'id' => $pacDataDb['id'],
                        'pack_code' => $item['name'],
                        'display' => $pacDataDb['brief'],
                        'type_xntvbh' => 2,
//                        'display' => $item['display'],
                        'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : $item['detail'],
                        'is_reg' => $isReg,
                        'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                        'is_bang_ma_098' => in_array($item['name'], $arrCode098) ? '1' : 0,
                        'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                        'label_reg' => $regType['label_reg'],
                        'label_unreg' => ucwords($regType['label_unreg']),
                        'confirm_reg' => $pacDataDb['confirm'] != '' ? $pacDataDb['confirm'] : str_replace('[package]', $item['display'], $regType['confirm_reg']),
                        'confirm_unreg' => $pacDataDb['confirmCancel'] != '' ? $pacDataDb['confirmCancel'] : str_replace('[package]', $item['display'], $regType['confirm_unreg']),
                        # nhatdv1 xhhbh
                        'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                        'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                        'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                        'socialSellType' => self::TYPE_GET_PROMOTION_DATA_USSD
                    );
                } else {
                    continue;
//                    $arrDataDay[] = array(
//                        'pack_code' => $item['name'],
//                        'type_xntvbh' => 2,
//                        'display' => $item['display'],
//                        'detail' => $item['detail'],
//                        'is_reg' => $isReg,
//                        'is_098' => 0,
//                        'is_bang_ma_098' => in_array($item['name'], $arrCode098) ? '1' : 0,
//                        'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
//                        'label_reg' => $regType['label_reg'],
//                        'label_unreg' => ucwords($regType['label_unreg']),
//                        'confirm_reg' => str_replace('[package]', $item['display'], $regType['confirm_reg']),
//                        'confirm_unreg' => str_replace('[package]', $item['display'], $regType['confirm_unreg']),
//                    );
                }
            }
        }
        // data_week
        $arrDataWeek = array();
        if ($arrListDataConfig['data_week']['show_list'] == '1') {
            foreach ($arrListDataConfig['data_week']['list_data'] as $item) {
                $isReg = ApiHelper::checkRegData(strtoupper($item['name']), $arrListReg) == true ? 1 : 0;
                if (ApiHelper::checkPackageDB(strtolower($item['name']), array_keys($listDataDb))) {
                    $pacDataDb = $listDataDb[strtolower($item['name'])];
                    $arrDataWeek[] = array(
                        'id' => $pacDataDb['id'],
                        'pack_code' => $item['name'],
                        'display' => $pacDataDb['brief'],
                        'type_xntvbh' => 2,
                        'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : $item['detail'],
                        'is_reg' => $isReg,
                        'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                        'is_bang_ma_098' => in_array($item['name'], $arrCode098) ? '1' : 0,
                        'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                        'label_reg' => $regType['label_reg'],
                        'label_unreg' => ucwords($regType['label_unreg']),
                        'confirm_reg' => $pacDataDb['confirm'] != '' ? $pacDataDb['confirm'] : str_replace('[package]', $item['display'], $regType['confirm_reg']),
                        'confirm_unreg' => $pacDataDb['confirmCancel'] != '' ? $pacDataDb['confirmCancel'] : str_replace('[package]', $item['display'], $regType['confirm_unreg']),
                        # nhatdv1 xhhbh
                        'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                        'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                        'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                        'socialSellType' => self::TYPE_GET_PROMOTION_DATA_USSD
                    );
                } else {
                    continue;
//                    $arrDataWeek[] = array(
//                        'pack_code' => $item['name'],
//                        'display' => $item['display'],
//                        'type_xntvbh' => 2,
//                        'detail' => $item['detail'],
//                        'is_reg' => $isReg,
//                        'is_098' => 0,
//                        'is_bang_ma_098' => in_array($item['name'], $arrCode098) ? '1' : 0,
//                        'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
//                        'label_reg' => $regType['label_reg'],
//                        'label_unreg' => ucwords($regType['label_unreg']),
//                        'confirm_reg' => str_replace('[package]', $item['display'], $regType['confirm_reg']),
//                        'confirm_unreg' => str_replace('[package]', $item['display'], $regType['confirm_unreg']),
//                    );
                }
            }
        }
        // data_month
        $arrDataMonth = self::getPackData($listDataUssd, $msisdn, $listDataDb, $arrListReg);
        if (!empty($arrDataMonth['webservices'])) {
            $webservices = array_merge($webservices, $arrDataMonth['webservices']);
        }
        $arrDataMonth = $arrDataMonth['arrPackage'];
        $arrList = array();
        foreach ($arrTypeConfig['list'] as $list) {
            $list_data = null;
            if ($list['type'] == 'mi_day') {
                $list_data = !empty($arrDataDay) ? $arrDataDay : null;
            } elseif ($list['type'] == 'mi_week') {
                $list_data = !empty($arrDataWeek) ? $arrDataWeek : null;
            } elseif ($list['type'] == 'mi_month') {
                $list_data = $arrDataMonth;
            }
            $arrList[] = array(
                'type' => $list['type'],
                'name' => $list['name'],
                'list_data' => $list_data,
            );
        }
        $arrAllData = array_merge($arrDataDay, $arrDataWeek, $arrDataMonth);
        $message = '';
        if (!count($arrAllData)) {
            $message = $i18n->__('Thuê bao không đủ điều kiện đăng ký gói Data nào');
        }
        $return = array(
            'arrReturn' => array(
                'data' => array(
                    'type' => $arrTypeConfig['type'],
                    'name' => $arrTypeConfig['name'],
                    'list' => !empty($arrList) ? $arrList : null,
                ),
                'message' => $message
            ),
            'webservices' => $webservices
        );
        return $return;
    }

    public function getPackDataNew($listDataUssd, $msisdn, $listDataDb, $arrListReg, $type = 'data_new')
    {
        $webservices = array();
        $client = new WsDataClient();
        $result = $client->getAddOnUSSD($msisdn, 3);
        $codeOrig = VTPHelper::getOriginalBccsGW("<return>" . $result . "</return>", "<return>", "</return>");
        $arrPackage = array();
        $regType = $listDataUssd[$type]['reg_type'];
        $listDetail = !empty($codeOrig->AddOnPackage->ListDetail->detail) ? $codeOrig->AddOnPackage->ListDetail->detail : [];
        //lay danh sach cac ma dich vu tren 098
        $resultCode098 = call('App.Viettel.Package098.getPackageDisplay');
        $arrCode098 = array();
        if (!empty($resultCode098)) {
            foreach ($resultCode098 as $item098) {
                $arrCode098[] = $item098['title'];
            }
        }
        if (is_object($listDetail)) {
            $listDetail = array($listDetail);
        }
        if ($type == 'data_addon') {
            $socialSellType = self::TYPE_GET_PROMOTION_DATA_USSD_ADDON;
        } else {
            $socialSellType = self::TYPE_GET_PROMOTION_DATA_USSD;
        }
        foreach ($listDetail as $package) {
            $isReg = ApiHelper::checkRegData(strtoupper($package->pkgCode), $arrListReg) == true ? 1 : 0;
            if (ApiHelper::checkPackageDB(strtolower($package->pkgCode), array_keys($listDataDb))) {
                $pacDataDb = $listDataDb[strtolower($package->pkgCode)];
                $tags = !empty($package->infoStr) ? self::replaceStringTags($package->infoStr) : '';
                $arrPackage[] = array(
                    'id' => $pacDataDb['id'],
                    'pack_code' => $package->pkgCode,
                    'display' => $pacDataDb['brief'],
                    'type_xntvbh' => 2,
                    'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : $package->description,
                    'is_reg' => $isReg,
                    'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                    'is_bang_ma_098' => in_array($package->pkgCode, $arrCode098) ? '1' : 0,
                    'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                    'label_reg' => $regType['label_reg'],
                    'label_unreg' => ucwords($regType['label_unreg']),
                    'confirm_reg' => $pacDataDb['confirm'] != '' ? $pacDataDb['confirm'] : str_replace('[package]', $package->pkgName, $regType['confirm_reg']),
                    'confirm_unreg' => $pacDataDb['confirmCancel'] != '' ? $pacDataDb['confirmCancel'] : str_replace('[package]', $package->pkgName, $regType['confirm_unreg']),
                    'is4G' => !empty($pacDataDb['is4G']) ? $pacDataDb['is4G'] : 0,
                    # nhatdv1 xhhbh
                    'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                    'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                    'socialSellType' => $socialSellType,
                    'tags' => $tags,
                    'price' => !empty($pacDataDb['price']) ? $pacDataDb['price'] : 0,
                    'image' => (isset($pacDataDb['image']) && $pacDataDb['image'] != '') ? 'http://media.vietteltelecom.vn/upload/' . $pacDataDb['image'] : null,
                    'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                );
            } else {
                continue;
//                $arrPackage[] = array(
//                    'pack_code' => $package->pkgCode,
//                    'display' => $package->pkgName,
//                    'type_xntvbh' => 2,
//                    'detail' => $package->description,
//                    'is_reg' => $isReg,
//                    'is_098' => 0,
//                    'is_bang_ma_098' => in_array($package->pkgCode, $arrCode098) ? '1' : 0,
//                    'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
//                    'label_reg' => $regType['label_reg'],
//                    'label_unreg' => ucwords($regType['label_unreg']),
//                    'confirm_reg' => str_replace('[package]', $package->pkgName, $regType['confirm_reg']),
//                    'confirm_unreg' => str_replace('[package]', $package->pkgName, $regType['confirm_unreg']),
//                    'tags' => $tags,
//                );
            }
        }
        $return = array(
            'arrPackage' => !empty($arrPackage) ? array(array('list_data' => $arrPackage)) : null,
            'webservices' => $webservices
        );
        return $return;
    }

    public function returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg, $type = 'data_new')
    {
        $i18n = $this->getContext()->getI18N();
        $webservices = array();
        $arrDataNew = self::getPackDataNew($listDataUssd, $msisdn, $listDataDb, $arrListReg, $type);
        if (!empty($arrDataNew['webservices'])) {
            $webservices = array_merge($webservices, $arrDataNew['webservices']);
        }
        $arrDataNew = $arrDataNew['arrPackage'];
        $arrTypeConfig = $listDataUssd[$type];
        $message = '';
        if (!count($arrDataNew)) {
            $message = $i18n->__('Thuê bao không đủ điều kiện đăng ký gói Data nào');
        }
        return array(
            'arrReturn' => array(
                'data' => array(
                    'type' => $arrTypeConfig['type'],
                    'name' => $arrTypeConfig['name'],
                    'list' => $arrDataNew,
                ),
                'message' => $message
            ),
            'webservices' => $webservices
        );
    }

    public function getPackDataAddon($listDataUssd, $msisdn, $listDataDb, $arrListReg)
    {
        $webservices = array();
        $client = new WsDataClient();
        $result = $client->getAddOnUSSD($msisdn, 1);
        $codeOrig = VTPHelper::getOriginalBccsGW("<return>" . $result . "</return>", "<return>", "</return>");
        $arrPackage = array();
        $regType = $listDataUssd['data_addon']['reg_type'];
        if (!empty($codeOrig->AddOnPackage) and is_array($codeOrig->AddOnPackage)) {
            //lay danh sach cac ma dich vu tren 098
            $resultCode098 = call('App.Viettel.Package098.getPackageDisplay');
            $arrCode098 = array();
            if (!empty($resultCode098)) {
                foreach ($resultCode098 as $item098) {
                    $arrCode098[] = $item098['title'];
                }
            }
            foreach ($codeOrig->AddOnPackage as $package) {
                $arrListItem = array();
                if ($package->ListDetail->detail) {
                    $listItemDetail = $package->ListDetail->detail;
                    if (!is_array($listItemDetail)) {
                        $listItemDetail = array($listItemDetail);
                    }
                    foreach ($listItemDetail as $item) {
                        $isReg = ApiHelper::checkRegData(strtoupper($item->pkgCode), $arrListReg) == true ? 1 : 0;
                        if (ApiHelper::checkPackageDB(strtolower($item->pkgCode), array_keys($listDataDb))) {
                            $pacDataDb = $listDataDb[strtolower($item->pkgCode)];
                            $tags = !empty($item->infoStr) ? self::replaceStringTags($item->infoStr) : '';
                            $arrListItem[] = array(
                                'id' => $pacDataDb['id'],
                                'pack_code' => $item->pkgCode,
                                'display' => $pacDataDb['brief'],
                                'type_xntvbh' => 2,
                                'detail' => $pacDataDb['description'],
                                'is_reg' => $isReg,
                                'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                                'is_bang_ma_098' => in_array($item->pkgCode, $arrCode098) ? '1' : 0,
                                'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
                                'label_reg' => $regType['label_reg'],
                                'label_unreg' => ucwords($regType['label_unreg']),
                                'confirm_reg' => $pacDataDb['regConfirm'] != '' ? $pacDataDb['regConfirm'] : str_replace('[package]', $item->pkgName, $regType['confirm_reg']),
                                'confirm_unreg' => $pacDataDb['unRegConfirm'] != '' ? $pacDataDb['unRegConfirm'] : str_replace('[package]', $item->pkgName, $regType['confirm_unreg']),
                                # nhatdv1 xhhbh
                                'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                                'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                                'socialSellType' => self::TYPE_GET_PROMOTION_DATA_USSD_ADDON,
                                'tags' => $tags,
                                'price' => !empty($pacDataDb['price']) ? $pacDataDb['price'] : 0,
                                'image' => (isset($pacDataDb['image']) && $pacDataDb['image'] != '') ? 'http://media.vietteltelecom.vn/' . $pacDataDb['image'] : null,
                                'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                            );
                        } else {
                            continue;
//                            $arrListItem[] = array(
//                                'pack_code' => $item->pkgCode,
//                                'display' => $item->pkgName,
//                                'type_xntvbh' => 2,
//                                'detail' => $item->description,
//                                'is_reg' => $isReg,
//                                'is_098' => 0,
//                                'is_bang_ma_098' => in_array($item->pkgCode, $arrCode098) ? '1' : 0,
//                                'type' => $isReg == 1 ? $regType['type_unreg'] : $regType['type_reg'],
//                                'label_reg' => $regType['label_reg'],
//                                'label_unreg' => ucwords($regType['label_unreg']),
//                                'confirm_reg' => str_replace('[package]', $item->pkgName, $regType['confirm_reg']),
//                                'confirm_unreg' => str_replace('[package]', $item->pkgName, $regType['confirm_unreg']),
//                            );
                        }
                    }
                }
                $arrPackage[] = array(
                    'name' => $package->name,
                    'description' => $package->description,
                    'list_data' => !empty($arrListItem) ? $arrListItem : null,
                );
            }
        }
        $return = array(
            'arrPackage' => !empty($arrPackage) ? $arrPackage : null,
            'webservices' => $webservices
        );
        return $return;
    }

    public function returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg)
    {
        $i18n = $this->getContext()->getI18N();
        $webservices = array();
        $arrDataAddon = self::getPackDataAddon($listDataUssd, $msisdn, $listDataDb, $arrListReg);
        if (!empty($arrDataAddon['webservices'])) {
            $webservices = array_merge($webservices, $arrDataAddon['webservices']);
        }
        $arrDataAddon = $arrDataAddon['arrPackage'];
        $arrTypeConfig = $listDataUssd['data_addon'];
        $message = '';
        if (!count($arrDataAddon)) {
            $message = $i18n->__('Thuê bao không đủ điều kiện đăng ký gói Addon nào');
        }
        return array(
            'arrReturn' => array(
                'data' => array(
                    'type' => $arrTypeConfig['type'],
                    'name' => $arrTypeConfig['name'],
                    'list' => $arrDataAddon,
                ),
                'message' => $message
            ),
            'webservices' => $webservices
        );
    }

    public function getPackDataPlus($listDataUssd, $msisdn, $type = 3)
    {
        $webservices = array();
        $client = new WsDataPlusClient();
        $result = $client->getAddDataUSSD($msisdn, $type);
        $codeOrig = VTPHelper::getOriginalBccsGW("<return>" . $result . "</return>", "<return>", "</return>");
        $arrPackage = array();
        $packages = !empty($codeOrig->package) ? $codeOrig->package : array();
        if (!is_array($packages) && $packages) {
            $packages = array($packages);
        }
        $regType = $listDataUssd['data_plus']['reg_type'];
        //lay danh sach cac ma dich vu tren 098
        $resultCode098 = call('App.Viettel.Package098.getPackageDisplay');
        $arrCode098 = array();
        if (!empty($resultCode098)) {
            foreach ($resultCode098 as $item098) {
                $arrCode098[] = $item098['title'];
            }
        }
        $listDataDbPlus = call('App.Viettel.InternetPackage.getAllPackageBuyData');
        foreach ($listDataDbPlus as $key => $itemDB) {
            $listDataDb[strtolower($key)] = $itemDB;
        }
        foreach ($packages as $package) {
            if (ApiHelper::checkPackageDB(strtolower($package->name), array_keys($listDataDb))) {
                $pacDataDb = $listDataDb[strtolower($package->name)];
                $tags = !empty($package->infoStr) ? self::replaceStringTags($package->infoStr) : '';
                $arrPackage[] = array(
                    'id' => $pacDataDb['id'],
                    'pack_code' => $package->name,
                    'display' => $pacDataDb['brief'],
                    'type_xntvbh' => 2,
                    'is_098' => !empty($pacDataDb['isDisplay098']) ? $pacDataDb['isDisplay098'] : 0,
                    'is_bang_ma_098' => in_array($package->name, $arrCode098) ? '1' : 0,
                    'detail' => $pacDataDb['description'] != '' ? $pacDataDb['description'] : $package->detail,
                    'type' => $regType['type'],
                    'label_reg' => $regType['label'],
                    'confirm_reg' => $pacDataDb['confirm'] != '' ? $pacDataDb['confirm'] : str_replace('[package]', $package->menu, $regType['confirm']),
                    # nhatdv1 xhhbh
                    'hoahong' => (isset($pacDataDb['hoahong']) && $pacDataDb['hoahong'] != '') ? $pacDataDb['hoahong'] : null,
                    'xhhbh' => empty($pacDataDb['hoahong']) ? false : XhhbnHelper::checkXhhbhInvite(true),
                    'socialSellType' => self::TYPE_GET_PROMOTION_DATA_USSD_PLUS,
                    'tags' => $tags,
                    'price' => !empty($pacDataDb['price']) ? $pacDataDb['price'] : 0,
                    'image' => (isset($pacDataDb['image']) && $pacDataDb['image'] != '') ? 'http://media.vietteltelecom.vn/' . $pacDataDb['image'] : null,
                    'ctt' => (isset($pacDataDb['s5']) && $pacDataDb['s5']) ? 0 : 1,
                );

            } else {
                continue;
//                $arrPackage[] = array(
//                    'pack_code' => $package->name,
//                    'display' => $package->menu,
//                    'type_xntvbh' => 2,
//                    'is_098' => 0,
//                    'is_bang_ma_098' => in_array($package->name, $arrCode098) ? '1' : 0,
//                    'detail' => $package->detail,
//                    'type' => $regType['type'],
//                    'label_reg' => $regType['label'],
//                    'confirm_reg' => str_replace('[package]', $package->menu, $regType['confirm']),
//                    'tags' => $tags
//                );
            }
        }
        $return = array(
            'arrPackage' => !empty($arrPackage) ? $arrPackage : null,
            'webservices' => $webservices
        );
        return $return;
    }

    public function returnPackDataPlus($listDataUssd, $msisdn, $listAll)
    {
        $i18n = $this->getContext()->getI18N();
        $webservices = array();
        // dplus_month
        $arrPlusMonth = self::getPackDataPlus($listDataUssd, $msisdn, 4);
        if (!empty($arrPlusMonth['webservices'])) {
            $webservices = array_merge($webservices, $arrPlusMonth['webservices']);
        }
        $arrPlusMonth = !empty($arrPlusMonth['arrPackage']) ? $arrPlusMonth['arrPackage'] : array();
        $arrTypeConfig = $listDataUssd['data_plus'];
        $arrList = array();
        foreach ($arrTypeConfig['list'] as $list) {
            $list_data = null;
            if ($list['type'] == 'plus_month') {
                $list_data = $arrPlusMonth;
            }
            $arrList[] = array(
                'type' => $list['type'],
                'name' => $list['name'],
                'list_data' => $list_data,
            );
        }
        if (!count($list_data)) {
            $message = $i18n->__('Thuê bao không đủ điều kiện đăng ký gói mua thêm nào');
        }

        return array(
            'arrReturn' => array(
                'data' => array(
                    'type' => $arrTypeConfig['type'],
                    'name' => $arrTypeConfig['name'],
                    'list' => !empty($arrList) ? $arrList : null,
                ),
                'message' => $message
            ),
            'webservices' => $webservices
        );
    }

    /**
     * huync2: Dang ky goi data ussd 191
     * @param sfWebRequest $request
     * @return string
     */
    public function executeRegisterDataUssd(sfWebRequest $request)
    {
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'RegisterData',
            'title' => $i18N->__('Đăng ký data'),
            'service' => 'apiv2.mobileInternet.executeRegisterDataUssd',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $package = $request->getParameter('package');
        $key = VtHelper::validateNullparams(array('type' => $type, 'package' => $package));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;

            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }
        $client = false;
        if ($type) {
            switch ($type) {
                case 'data': {
                    $client = new MobileInternetClient();
//                    call('Web.ActionLog.startTimer');
//                    $current_pakage = $client->checkMI($msisdn);
//                    $webservices[] = call('Web.ActionLog.getWserviceLog', array('wsCode' => 'MobileInternetClient:checkMI'));
//                    if ($current_pakage == $package) {
//                        $result['errorCode'] = 2;
//                        $result['message'] = $i18N->__('Đăng ký thất bại do Quý khách đang sử dụng gói cước %package%!', array('%package%' => $current_pakage));
//                        $result['data'] = null;
//                        if (!empty($result)) {
//                            $logFields['results'] = json_encode($result);
//                        }
//                        if (!empty($webservices)) {
//                            $logFields['webservices'] = json_encode($webservices);
//                        }
//                        call('Web.ActionLog.startTimer', array('timer' => $timer));
//                        call('Web.ActionLog.edit', array(
//                            'fields' => $logFields,
//                        ));
//                        return $this->renderText(json_encode($result));
//                    }
                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    $checkData = self::checkPackage($package);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );

                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }
                    $result = $client->registerMI($msisdn, $package);
                }
                    break;
                case 'data_new': {
                    $client = new WsDataClient();
                    $listDataAddon = self::getListPackDataAddon($msisdn);
                    if (!empty($listDataAddon['webservices'])) {
                        $webservices = array_merge($webservices, $listDataAddon['webservices']);
                    }
                    $listDataAddon = $listDataAddon['arrPackage'];
                    $item = !empty($listDataAddon[strtolower($package)]) ? $listDataAddon[strtolower($package)] : '';
                    if ($item['type'] == '1') {
                        $client = new MobileInternetClient();
                        call('Web.ActionLog.startTimer');
                        # huync2: kiem tra goi Data trong DB truoc khi dang ky
                        $checkData = self::checkPackage($package);
                        if ($checkData == false) {
                            sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                            $arrReturn = ApiHelper::formatResponse(
                                ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                            );

                            if (!empty($arrReturn)) {
                                $logFields['results'] = json_encode($arrReturn);
                            }

                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($arrReturn));
                        }
                        $result = $client->registerMI($msisdn, $package);
//                        $result = $client->registerData3gV2($msisdn, $package);
                    } elseif ($item['type'] == '0') {
                        # huync2: kiem tra goi Data trong DB truoc khi dang ky
//        $checkData = Data('InternetPackage')->useIndex('code')->select(array('code' => $package));
                        $checkData = self::checkPackage($package, 4);
                        if ($checkData == false) {
                            sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                            $arrReturn = ApiHelper::formatResponse(
                                ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                            );

                            if (!empty($arrReturn)) {
                                $logFields['results'] = json_encode($arrReturn);
                            }

                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($arrReturn));
                        }
                        $result = $client->registerAddOn($msisdn, $package);
                    } else {
                        $result = false;
                    }
                }
                    break;
                case 'data_addon': {
                    $client = new WsDataClient();
                    call('Web.ActionLog.startTimer');

                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    $checkData = self::checkPackage($package, 4);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );

                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }

                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }
                    $result = $client->registerAddOn($msisdn, $package);
                }
                    break;
                case 'data_plus': {
                    $client = new WsDataPlusClient();

                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    // $checkData = Data('InternetPackage')->useIndex('code')->select(array('code' => $package));
                    $checkData = self::checkPackage($package, 3);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );

                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }

                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }
                    $result = $client->BuyData($msisdn, $package);
                }
                    break;
                // huync2: dang ky VTFree
                case 'vtfree': {
                    $logFields['actionType'] = 'RegisterVtfree';
                    $promotion = Data('Product')->select(array('code' => $package));
                    if (!$promotion) {
                        $result['errorCode'] = 4;
                        $result['message'] = $i18N->__('Khuyến mãi không tồn tại');
                        $result['data'] = null;
                        call('Web.ActionLog.startTimer', array('timer' => $timer));
                        if (!empty($webservices)) {
                            $logFields['webservices'] = json_encode($webservices);
                        }

                        if (!empty($result)) {
                            $logFields['results'] = json_encode($result);
                        }
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($result));
                    }
                    $client = new ListVasClient();
                    $result = $client->regVtFree($msisdn, $package);
                }
                    break;
                default: {
                    $result = false;
                }
            }
        }
        $message = $i18N->__('Đăng ký gói cước không thành công.');
        if ($client) {
            if ($type == 'vtfree') {
                $message = (isset($result['des']) && $result['des'] != '') ? $result['des'] : $i18N->__('Đăng ký gói cước không thành công.');
            } else {
                $message = ($client->getErrorMessage() == '') ? $message : $client->getErrorMessage();
            }
        }
        if ($result) {
            $errCode = 0;
            if ($type == 'vtfree') {
                $errCode = (($result) ? $result['errCode'] : 1);
            }
            $arrReturn = ApiHelper::formatResponse(
                $errCode, null, $message
            );
        } else {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $message
            );
        }

        if (!empty($arrReturn)) {
            $logFields['results'] = json_encode($arrReturn);
        }

        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        return $this->renderText(json_encode($arrReturn));
    }

    /**
     * huync2: Huy dang ky goi data ussd 191
     * @param sfWebRequest $request
     * @return string
     */
    public function executeUnregisterDataUssd(sfWebRequest $request)
    {
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'UnregisterData',
            'title' => $i18N->__('Hủy đăng ký data ussd'),
            'service' => 'apiv2.mobileInternet.executeUnregisterDataUssd',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $package = $request->getParameter('package');
        $renewed = $request->getParameter('renewed', 0);
        $key = VtHelper::validateNullparams(array('type' => $type, 'package' => $package));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;

            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }

            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));

            return $this->renderText(json_encode($result));
        }
        $client = false;
        if ($type) {
            //Lay danh sach goi duoc dang ky nhieu lan
            $data3GNoGWClient = new data3GNoGWClient();
            $responseWsData098 = $data3GNoGWClient->getAllDataInfo();
            $arrCancelExtend = array();
            if($responseWsData098){
                foreach ($responseWsData098 as $res){
                    if ($res->isCancelExtend == true){
                        $arrCancelExtend[] = $res->name;
                    }
                }
            }
            switch ($type) {
                case 'data': {
                    //nguyetNT32: chặn 4 gói 0 mất tiền
                    $arrPackege = array('MIMIN', 'MI0', 'MIMAX0', 'DC0');
                    if (in_array(strtoupper($package), $arrPackege)) {
                        $result['errorCode'] = 3;
                        $result['message'] = $i18N->__('Gói cước này hiện không được hủy trên My Viettel, vui lòng thử lại sau!');
                        $result['data'] = null;
                        if (!empty($result)) {
                            $logFields['results'] = json_encode($result);
                        }

                        call('Web.ActionLog.startTimer', array('timer' => $timer));
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));

                        return $this->renderText(json_encode($result));
                    }

                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    $checkData = self::checkPackage($package);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkUnRegData apiv2.mobileInternet.executeUnregisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );

                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }

                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }

                    //neu la huy gia han
                    if($renewed || in_array(strtoupper($package), $arrCancelExtend)){
                        $client = new DataWS();
                        $result = $client->cancelExtend($msisdn, $package);
                    }else{
                        //huy han goi cuoc
                        $client = new MobileInternetClient();
                        $result = $client->unRegisterMI($msisdn, $package);
                    }

                }
                    break;
                case 'data_new': {
//                    $client = new WsDataClient();
                    $listDataAddon = self::getListPackDataAddon($msisdn);
                    if (!empty($listDataAddon['webservices'])) {
                        $webservices = array_merge($webservices, $listDataAddon['webservices']);
                    }
                    $listDataAddon = $listDataAddon['arrPackage'];
                    $item = !empty($listDataAddon[strtolower($package)]) ? $listDataAddon[strtolower($package)] : '';
                    if ($item['type'] == '1') {

                        //nguyetNT32: chặn 4 gói 0 mất tiền
                        $arrPackege = array('MIMIN', 'MI0', 'MIMAX0', 'DC0');
                        if (in_array(strtoupper($package), $arrPackege)) {
                            $result['errorCode'] = 3;
                            $result['message'] = $i18N->__('Gói cước này hiện không được hủy trên My Viettel, vui lòng thử lại sau!');
                            $result['data'] = null;

                            if (!empty($result)) {
                                $logFields['results'] = json_encode($result);
                            }

                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($result));
                        }

                        # huync2: kiem tra goi Data trong DB truoc khi dang ky
                        $checkData = self::checkPackage($package);
                        if ($checkData == false) {
                            sfContext::getInstance()->getLogger()->log('checkUnRegData apiv2.mobileInternet.executeUnregisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                            $arrReturn = ApiHelper::formatResponse(
                                ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                            );

                            if (!empty($arrReturn)) {
                                $logFields['results'] = json_encode($arrReturn);
                            }

                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($arrReturn));
                        }
                        //neu la huy gia han
                        if($renewed || in_array(strtoupper($package), $arrCancelExtend)){
                            $client = new DataWS();
                            $result = $client->cancelExtend($msisdn, $package);
                        }else{
                            //huy han goi cuoc
                            $client = new MobileInternetClient();
                            $result = $client->unRegisterMI($msisdn, $package);
                        }

                    } elseif ($item['type'] == '0') {
                        # huync2: kiem tra goi Data trong DB truoc khi dang ky
                        $checkData = self::checkPackage($package, 4);
                        if ($checkData == false) {
                            sfContext::getInstance()->getLogger()->log('checkUnRegData apiv2.mobileInternet.executeUnregisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                            $arrReturn = ApiHelper::formatResponse(
                                ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                            );

                            if (!empty($arrReturn)) {
                                $logFields['results'] = json_encode($arrReturn);
                            }

                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($arrReturn));
                        }
                        //neu la huy gia han
                        if($renewed || in_array(strtoupper($package), $arrCancelExtend)){
                            $client = new DataWS();
                            $result = $client->cancelExtend($msisdn, $package);
                        }else{
                            //huy han goi cuoc
                            $client = new WsDataClient();
                            $result = $client->unregisterAddOn($msisdn, $package);
                        }

                    } else {
                        $result = false;
                    }
                }
                    break;
                case 'data_addon': {
                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    $checkData = self::checkPackage($package, 4);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkUnRegData apiv2.mobileInternet.executeUnregisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );

                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }

                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }
                    //neu la huy gia han
                    if($renewed || in_array(strtoupper($package), $arrCancelExtend)){
                        $client = new DataWS();
                        $result = $client->cancelExtend($msisdn, $package);
                    }else{
                        //huy han goi cuoc
                        $client = new WsDataClient();
                        $result = $client->unregisterAddOn($msisdn, $package);
                    }
                }
                    break;
                case 'data_plus': {
                    // nothing
                    $result = false;
                }
                    break;
                default: {
                    $result = false;
                }
            }
        }

        $message = $i18N->__('Hủy gói cước không thành công.');
        if ($client) {
            $message = ($client->getErrorMessage() == '') ? $message : $client->getErrorMessage();
        }
        if ($result) {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::SUCCESS, null, $message
            );
        } else {
            sfContext::getInstance()->getLogger()->log('checkUnRegData apiv2.mobileInternet.executeUnregisterDataUssd result false: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $message
            );
        }
//        if (!empty($arrReturn)) {
//            $logFields['results'] = ($arrReturn['errorCode'] == 0) ? 'SUCCESS' : 'FAIL';
//        }

        if (!empty($arrReturn)) {
            $logFields['results'] = json_encode($arrReturn);
        }

        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        return $this->renderText(json_encode($arrReturn));
    }

    public function executeGetPromotionDataUssd(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $result = array();
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $telType = trim($request->getParameter('telType'));
        if (!empty($telType) and $telType == 'dcom') {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, [], $i18N->__('Dịch vụ không áp dụng cho thuê bao.')
            );
            return $this->renderText(json_encode($arrReturn));
        }
        $key = VtHelper::validateNullparams(array('type' => $type));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }

        $listAll = $request->getParameter('list_all');
        $listDataUssd = sfConfig::get('app_list_data_ussd');
        $arrReturn = null;
        $message = null;


        // data
        $client = new WsDataClient();
        $strPackReg = $client->checkData3gV2($msisdn);
        $arrListReg = array();
        $listDataDb = array();
        if ($strPackReg) {
            $arrListReg = explode('|', strtoupper($strPackReg));
        }

        $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.MobileInternet'
        ));
        $listDataDbDcom = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Dcom'
        ));
        if (!empty($listDataDbMi)) {
            foreach ($listDataDbMi as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        if (!empty($listDataDbDcom)) {
            foreach ($listDataDbDcom as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        // end data
        // addon
        $strPackRegAddon = $client->checkDataAddon($msisdn);
        if ($strPackRegAddon) {
            $arrListReg = array_merge($arrListReg, explode('|', strtoupper($strPackRegAddon)));
        }
        $listDataDbAddon = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Addon'
        ));
        if (!empty($listDataDbAddon)) {
            foreach ($listDataDbAddon as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }

        $arrListRegNew = array();
        foreach ($arrListReg as $itemReg) {
            if (!empty($listDataDb[strtolower($itemReg)])) {
                $dataItemReg = $listDataDb[strtolower($itemReg)];
                $arrListRegNew[] = !empty($dataItemReg['code']) ? $dataItemReg['code'] : '';
            } else {
                $arrListRegNew[] = $itemReg;
            }
        }
        $arrListReg = $arrListRegNew;

        if ($type) {
            switch ($type) {
                case 'data': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $arrReturn = $rtPackData['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = [];
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_new': {
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $arrReturn = $rtPackDataNew['data'];
                }
                    break;
                case 'data_addon': {
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $arrReturn = $rtPackDataAddon['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = [];
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_plus': {
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturn = $rtPackDataPlus['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = [];
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_all': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturnData[0] = $rtPackData['data'];
                    $arrReturnData[1] = $rtPackDataNew['data'];
                    $arrReturnData[2] = $rtPackDataAddon['data'];
                    $arrReturnData[3] = $rtPackDataPlus['data'];
                    $arrReturn = [];
                    for ($i = 0; $i < 4; $i++) {
                        $list = array();
                        if ($listAll) {
                            foreach ($arrReturnData[$i]['list'] as $item) {
                                if ($item['list_data'])
                                    $list = array_merge($list, $item['list_data']);
                            }
                            if (!count($list)) {
                                $list = [];
                            }
                            $arrReturnData[$i]['list'] = $list;
                        }
                        $arrReturn[] = $arrReturnData[$i];
                    }
                }
                    break;
                default: {
                    $arrReturn = [];
                }
            }
        }

        $arrReturn = ApiHelper::formatResponse(
            ApiResponseCode::SUCCESS, $arrReturn
        );
        return $this->renderText(json_encode($arrReturn));
    }

    // VtpUserVsaTable::checkAccount($msisdn)
    /**
     * huync2: Lay sanh sach goi data ussd 191 theo sdt
     * @param sfWebRequest $request
     * @return string
     */

    public function executeGetPromotionDataUssdVsa(sfWebRequest $request)
    {
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        $i18N = $this->getContext()->getI18N();
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $result = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $logFields = array(
            'logType' => 'Selfcare',
            'actionType' => 'TVBH',
            'title' => $i18N->__('Tư vấn bán hàng'),
            'service' => 'apiv2.mobileInternet.executeGetPromotionDataUssdVsa',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if (!$userVsa = Data('UserVsa')->select(user()->id)) {
            if (strval($userVsa['status']) != '1') {
                $arrReturn = ApiHelper::formatResponse(
                    ApiResponseCode::ERROR, null, $i18N->__('Không có quyền truy cập!')
                );
                return $this->renderText(json_encode($arrReturn));
            }
        }
        $msisdn = trim($request->getParameter('msisdn'));
        if (!$msisdn) {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18N->__('Bạn chưa nhập số điện thoại!')
            );
            return $this->renderText(json_encode($arrReturn));
        }
        $msisdn = VTPHelper::formatMobileNumber($msisdn, VTPHelper::MOBILE_849x);

        // check Dcom
        $clientMob = new VtpGetSubInfoMob();
        $subInfo = $clientMob->getSubInfo($msisdn);

        // kiem tra thue bao hop le, fix lỗi ngày 30/10/2017
        if ($subInfo == 'NO_INFO_SUB' || $subInfo === false) {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18N->__('Thuê bao nhập vào không hợp lệ.')
            );

            $logFields['results'] = json_encode(array(
                'errorCode' => '1',
                'message' => $i18N->__('Thuê bao không hợp lệ, hoặc không tồn tại, msisdn: ' . $msisdn)
            ));
            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($arrReturn));
        }
        $arrDcomProductCode = explode(',', strtoupper(sfConfig::get('app_dcom_product_code')));

        if (in_array(strtoupper($subInfo['PRODUCT_CODE']), $arrDcomProductCode)) {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18N->__('Dịch vụ không áp dụng cho thuê bao.')
            );
            return $this->renderText(json_encode($arrReturn));
        }
        //check thue bao kich hoat sau ngay 01/01/2016 thi khong hien thi chuong trinh khuyen mai nao
        $checkOff = MVPHelper::checkPromotionMsisdnOff($msisdn);
        if ($checkOff) {
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = array();
            $result['special'] = array();
            return $this->renderText(json_encode($result));
        }
        // data
        $client = new WsDataClient();
        $strPackReg = $client->checkData3gV2($msisdn);
        $arrListReg = array();
        $listDataDb = array();
        if ($strPackReg) {
            $arrListReg = explode('|', strtoupper($strPackReg));
        }

        $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.MobileInternet',
            //'onlyMass' => true,
        ));
        $listDataDbDcom = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Dcom',
            //'onlyMass' => true,
        ));

        $listDataDbBuy = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.BuyData',
            //'onlyMass' => true,
        ));
        if (!empty($listDataDbBuy)) {
            foreach ($listDataDbBuy as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        if (!empty($listDataDbMi)) {
            foreach ($listDataDbMi as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        if (!empty($listDataDbDcom)) {
            foreach ($listDataDbDcom as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        // end data
        // addon
        $strPackRegAddon = $client->checkDataAddon($msisdn);
        if ($strPackRegAddon) {
            $arrListReg = array_merge($arrListReg, explode('|', strtoupper($strPackRegAddon)));
        }

        $listDataDbAddon = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Addon',
            //'onlyMass' => true,
        ));
        if (!empty($listDataDbAddon)) {
            foreach ($listDataDbAddon as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        // end addon
        $arrListRegNew = array();
        foreach ($arrListReg as $itemReg) {
            if (!empty($listDataDb[strtolower($itemReg)])) {
                $dataItemReg = $listDataDb[strtolower($itemReg)];
                $arrListRegNew[] = !empty($dataItemReg['code']) ? $dataItemReg['code'] : '';
            } else {
                $arrListRegNew[] = $itemReg;
            }
        }
        $arrListReg = $arrListRegNew;

        $type = trim($request->getParameter('type'));
        $key = VtHelper::validateNullparams(array('type' => $type));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = [];
            return $this->renderText(json_encode($result));
        }
        $listAll = trim($request->getParameter('list_all'));
        $listDataUssd = sfConfig::get('app_list_data_ussd');
        $arrReturn = [];
        $message = null;
        if ($type) {
            switch ($type) {
                case 'data': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $arrReturn = $rtPackData['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = [];
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_new': {
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $arrReturn = $rtPackDataNew['data'];
                }
                    break;
                case 'data_addon': {
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $arrReturn = $rtPackDataAddon['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = [];
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_plus': {
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturn = $rtPackDataPlus['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = [];
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_all': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturnData[0] = $rtPackData['data'];
                    $arrReturnData[1] = $rtPackDataNew['data'];
                    $arrReturnData[2] = $rtPackDataAddon['data'];
                    $arrReturnData[3] = $rtPackDataPlus['data'];
                    $arrReturn = null;
                    for ($i = 0; $i < 4; $i++) {
                        $list = array();
                        if ($listAll) {

                            foreach ($arrReturnData[$i]['list'] as $item) {
                                //kiem tra goi cu co phai goi mass khong
                                $arrMass = array();
                                if ($item['list_data']) {
                                    $start = 0;
                                    foreach ($item['list_data'] as $mass) {
                                        if (array_key_exists(strtolower($mass['pack_code']), $listDataDb)) {
                                            $arrMass[$start] = $mass;
                                            $start++;
                                        }
                                    }
                                    $list = array_merge($list, $arrMass);
                                }
                            }
                            if (!count($list)) {
                                $list = [];
                            }
                            $arrReturnData[$i]['list'] = $list;
                        }
                        $arrReturn[] = $arrReturnData[$i];
                    }
                }
                    break;
                default: {
                    $arrReturn = [];
                }
            }
        }
        $dataAvailablePackages = array();
        if ($type == 'data_all') {
            foreach ($arrReturn as $idx => $value) {
                if (!empty($value['list'])) {
                    foreach ($value['list'] as $idx1 => $value1) {
                        $dataAvailablePackages[] = strtoupper($value1['pack_code']);
                    }
                }
            }
        } elseif (!empty($arrReturn['list'])) {
            foreach ($arrReturn['list'] as $idx1 => $value1) {
                if (!empty($value1['list_data'])) {
                    foreach ($value1['list_data'] as $idx2 => $value2) {
                        if (!empty($value2['pack_code'])) {
                            $dataAvailablePackages[] = strtoupper($value2['pack_code']);
                        }
                    }
                }
            }
        }
        //Lay danh sach goi cuoc dang sử dụng
        $data3GNoGWClient = new data3GNoGWClient();
        $currentPakage = strtoupper(ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn)));
        $client3G = new data3GNoGWClient();
        $reponseAddOn = $client3G->checkDataAddon($msisdn);
        $arrAddonUsed = array();
        if ($reponseAddOn) {
            $reponseAddOn = rtrim($reponseAddOn, "|");
            $arrAddonUsed = explode("|", $reponseAddOn);
        }
        //offer 3 goi tot nhat cho khach hang
        $arrNoData = array('MIMD', 'MIMAX0QT', 'I0', 'I.0', 'MI0');
        $arrData = array('MIMAX25', 'MIMAXCM', 'MIMAXBL', '3GQUETOI', 'VTTEEN1', 'VTTEEN2', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'CB60', 'MISV65', 'CAMAU', 'BACLIEU', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');
        $list1 = array('V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800', 'MIMAXCM,MIMAXBL', 'CB60', 'CAMAU,BACLIEU', 'MIMAX25', '3GQUETOI', 'VTTEEN1', 'VTTEEN2', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'MISV65', 'VTTVUI');
        $list2 = array('MIMAX25', 'MIMAXCM', 'MIMAXBL', '3GQUETOI', 'VTTEEN1', 'VTTEEN2', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'CB60', 'MISV65', 'CAMAU', 'BACLIEU', 'MIMAX1.5', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list3 = array('FB1N', 'YT1', 'FB7', 'FB30', 'YT30');
        $list4 = array('VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');
        $list5Mi = array('MIMAX90', 'MIMAX', 'MIMAX25', 'MIMAX35', 'MIMAXS', 'MIMAXSV');
        $list5Current = array('FB1N', 'YT1', 'FB7', 'MIMAX25', 'FB30', 'YT30', '3GQUETOI ', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'MISV65', 'MIMAX.5', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list5 = array('VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list6 = array('V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');
        $list7Mi = array('DMAX', 'MIMAX25', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'MISV65', 'MIMAX1.5');
        $list7Current = array('FB1N', 'YT1', 'FB7', 'MIMAX25', 'FB30', 'YT30', '3GQUETOI', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE ', 'MISV65', 'MIMAX1.5', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list7 = array('V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');

        $items = array();
        $check = 0;
        $checkCase = true;
        //Trường hợp thuê bao chưa đăng ký gói data nào
        if (in_array($currentPakage, $arrNoData)) {
            $arrData = $list1;
            $checkCase = false;
        }
        //check goi cuoc thuoc list2 thi hien thi list2
        if (in_array($currentPakage, $list2) && $checkCase) {
            $arrData = $list2;
            $checkCase = false;
        }
        //neu khach hang dang su dung cac goi addon
        if (in_array($currentPakage, $arrNoData) && (in_array("FB1N", $arrAddonUsed) || in_array("YT1", $arrAddonUsed) || in_array("FB7", $arrAddonUsed) || in_array("FB30", $arrAddonUsed) || in_array("YT30", $arrAddonUsed) && $checkCase)) {
            $arrData = $list2;
            $checkCase = false;
        }
        //neu dang tham gia goi mimax90 hoac mimax
        if (in_array($currentPakage, $list5Mi) && (in_array("FB1N", $arrAddonUsed) || in_array("YT1", $arrAddonUsed) || in_array("FB7", $arrAddonUsed) || in_array("FB30", $arrAddonUsed) || in_array("YT30", $arrAddonUsed) || in_array("3GQUETOI", $arrAddonUsed) || in_array("VTTRE", $arrAddonUsed) || in_array("VTVUI", $arrAddonUsed) || in_array("VTKHOE", $arrAddonUsed)) && $checkCase) {
            $arrData = $list5;
            $checkCase = false;
        }
        //dang dung goi DMAX
        if ($currentPakage == 'DMAX' && $checkCase) {
            $arrData = $list6;
            $checkCase = false;
        }
        //check list 7
        if (in_array($currentPakage, $list7Mi) && $checkCase) {
            $arrData = $list6;
            $checkCase = false;
        }
        //lay ra 3 goi data tốt nhất cho khách hàng
        if (($key = array_search($currentPakage, $arrData)) !== false) {
            unset($arrData[$key]);
        }
        $arrCheck = array_intersect($arrData, $dataAvailablePackages);
        if (count($arrCheck)) {
            foreach ($arrCheck as $ar) {
                if (count($items < 3)) {
                    $items[] = $ar;
                    break;
                }
            }
        }
        //lay lai danh sach 3 goi data tot nhat va sap xep
        $arr191 = array();
        $arrItemSave = array();
        if (count($items)) {
            foreach ($items as $it) {
                //map voi danh sach cac goi data trong db de lay noi dung
                if (count($arrReturn)) {
                    foreach ($arrReturn as $data1) {
                        if (!empty($data1['list'])) {
                            foreach ($data1['list'] as $data2) {
                                if ($it == strtoupper($data2['pack_code']) && !in_array($it, $arrItemSave)) {
                                    $arr191[] = $data2;
                                    $arrItemSave[] = $it;
                                }

                            }
                        }
                    }
                }
            }
        }

        $arrResult['errorCode'] = '0';
        $arrResult['message'] = $i18N->__('Thành công');

        if (!empty($arrReturn)) {
            $arrResult['data'] = $arrReturn;
            $logFields['results'] = json_encode($arrReturn);

        } else {
            $result['data'] = [];
        }
        if (!empty($arr191)) {
            $arrResult['special'] = $arr191;
        } else {
            $arrResult['special'] = [];
        }
        $logFields['results'] = json_encode(array(
            'errorCode' => '0',
            'message' => $i18N->__('Thành công.')
        ));
        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        return $this->renderText(json_encode($arrResult));
    }

    public function getListPackDataAddon($msisdn)
    {
        $webservices = array();
        $client = new WsDataClient();
        $result = $client->getAddOnUSSD($msisdn, '3,6');
        $codeOrig = VTPHelper::getOriginalBccsGW("<return>" . $result . "</return>", "<return>", "</return>");
        $arrPackage = null;
        $listDetail = $codeOrig->AddOnPackage->ListDetail->detail;
        if (is_object($listDetail)) {
            $listDetail = array($listDetail);
        }
        foreach ($listDetail as $package) {
            $arrPackage[strtolower($package->pkgCode)] = [
                'pkgCode' => $package->pkgCode,
                'pkgName' => $package->pkgName,
                'description' => $package->description,
                'type' => $package->type,
            ];
        }
        return array(
            'arrPackage' => $arrPackage,
            'webservices' => $webservices
        );
    }

    /**
     * huync2: nap the cao data
     * @param sfWebRequest $request
     * @return sfView|string
     */
    public function executeTopupData(sfWebRequest $request)
    {
        $timer = call('Web.ActionLog.startTimer');
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'TopupData',
            'title' => lang(''),
            'service' => 'apiv2.mobileInternetActions.executeTopupData',
            'objectId' => user()->id,
            'objectTitle' => '',
            'webservices' => '',
            'inputs' => $inputs,
            'content' => '',
            'results' => '',
        );
        $i18N = $this->getContext()->getI18N();
        $msisdn = user()->id;
//		$vtpUser = Data('Account')->select($msisdn);
        $cardPin = $request->getParameter('cardPin');
        $sid = $request->getParameter('sid');
        $captcha = $request->getParameter('captcha');
        //kiem tra captcha
        session_write_close();
        session_id($sid);
        session_start();
        if (!call('App.Viettel.Common.checkCaptcha', array('captcha' => $captcha, 'sid' => $sid))) {
            $result['errorCode'] = -4;
            $result['message'] = $i18N->__('Mã bảo mật không chính xác');
            $result['data'] = null;
            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }
            if (!empty($webservices)) {
                $logFields['webservices'] = json_encode($webservices);
            }
            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }
        $client = new WsDataClient();
        $result = $client->topupData($msisdn, $cardPin);
        if ($result) {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::SUCCESS, null, $i18N->__('Giao dịch của Quý khách đã được ghi nhận. Quý khách vui lòng chờ tin nhắn thông báo từ hệ thống.')
            );
        } else {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $client->getErrorMessage()
            );
        }
        if (!empty($arrReturn)) {
            $logFields['results'] = json_encode($arrReturn);
        }
        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        return $this->renderText(json_encode($arrReturn));
    }

    /**
     * huync2: lay danh sach luu luong con lai
     * @param sfWebRequest $request
     * @return sfView
     */
    public function executeCheckDataRemain4MyViettel(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $msisdn = user()->id;
        $client = new WsDataPlusClient();
        $items = $client->checkDataRemain4MyViettel($msisdn);
        $arr = array();
        if ($items) {
            $data3GNoGWClient = new data3GNoGWClient();
            $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));
            foreach ($items as $key => $value) {
                $arrItem = array();
                $arrItem['accId'] = $value->accId;
                $arrItem['accName'] = !empty($value->accName) ? str_replace(array('kho?n', 'ph?'), array($i18N->__('khoản'), $i18N->__('phụ')), $value->accName) : $value->accId;
                $arrItem['expireDate'] = $value->expireDate;
                $arrItem['remain'] = round($value->remain / 1024) . ' MB';
                if ($key == 0) {
                    if (empty($value->accName)) {
                        if ($value->accId != 'PCRF_MAIN' && !is_numeric($value->accId)) {
                            $arrItem['accName'] = $value->accId;
                        }
                    }
                    if (!empty($value->accId) && $value->accId == 28) {
                        $arrItem['accName'] = $currentPakage;
                    }

                    if (($value->accId == 'PCRF_MAIN' || $value->accId == 13) && $value->accName != '--') {
                        $arrItem['accName'] = $currentPakage;
                    }
                }
                $arr[] = $arrItem;
            }

            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::SUCCESS, $arr
            );
        } else {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null
            );
        }
        return $this->renderText(json_encode($arrReturn));
    }

    //lay ra danh sach cac goi tot nhat cho khach hang
    public function executeGetPromotionDataUssdV2(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $timer = call('Web.ActionLog.startTimer');
        $result = array();
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $telType = trim($request->getParameter('telType'));
        if (!empty($telType) and $telType == 'dcom') {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18N->__('Dịch vụ không áp dụng cho thuê bao.')
            );
            return $this->renderText(json_encode($arrReturn));
        }
        $key = VtHelper::validateNullparams(array('type' => $type));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }

        //check thue bao kich hoat sau ngay 01/01/2016 thi khong hien thi chuong trinh khuyen mai nao
        $checkOff = MVPHelper::checkPromotionMsisdnOff($msisdn);
        if ($checkOff) {
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = array();
            $result['special'] = array();
            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }
            if (!empty($webservices)) {
                $logFields['webservices'] = json_encode($webservices);
            }
            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }

        $listAll = $request->getParameter('list_all');
        $listDataUssd = sfConfig::get('app_list_data_ussd');
        $arrReturn = null;
        $message = null;
        // data
        $client = new WsDataClient();
        $strPackReg = $client->checkData3gV2($msisdn);
        $arrListReg = array();
        $listDataDb = array();
        if ($strPackReg) {
            $arrListReg = explode('|', strtoupper($strPackReg));
        }

        $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.MobileInternet'
        ));
        $listDataDbDcom = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Dcom'
        ));
        if (!empty($listDataDbMi)) {
            foreach ($listDataDbMi as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        if (!empty($listDataDbDcom)) {
            foreach ($listDataDbDcom as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        // end data
        // addon
        $strPackRegAddon = $client->checkDataAddon($msisdn);
        if ($strPackRegAddon) {
            $arrListReg = array_merge($arrListReg, explode('|', strtoupper($strPackRegAddon)));
        }
        $listDataDbAddon = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Addon'
        ));
        //lưu danh sách mã các gói addon
        $arrCodeAddon = array();
        $allAddonDb = array();
        if (!empty($listDataDbAddon)) {
            foreach ($listDataDbAddon as $key => $itemDB) {
                if ($itemDB['status'] == 1) {
                    $arrCodeAddon[] = $itemDB['code'];
                }
                $allAddonDb[strtolower($key)] = $itemDB;
            }
        }
        $arrListRegNew = array();
        foreach ($arrListReg as $itemReg) {
            if (!empty($listDataDb[strtolower($itemReg)])) {
                $dataItemReg = $listDataDb[strtolower($itemReg)];
                $arrListRegNew[] = !empty($dataItemReg['code']) ? $dataItemReg['code'] : '';
            } else {
                $arrListRegNew[] = $itemReg;
            }
        }
        $arrListReg = $arrListRegNew;

        if ($type) {
            switch ($type) {
                case 'data': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $arrReturn = $rtPackData['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_new': {
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $arrReturn = $rtPackDataNew['data'];
                }
                    break;
                case 'data_addon': {
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $arrReturn = $rtPackDataAddon['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_plus': {
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturn = $rtPackDataPlus['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_all': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $rtPackDataAddon = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $allAddonDb, $arrListReg, 'data_addon');
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    //lấy ra các gói 4G
                    $arrData4G = array();
                    if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                        foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                            //lấy ra gói 4G
                            if ($a['is4G'] == 1) {
                                $arrData4G[] = $a;
                            }
                        }
                    }
                    //lay danh sach cac goi addon dang duoc khuyen mai
                    $arrAddUsed = array();
                    if (count($rtPackDataAddon['data']['list'])) {
                        foreach ($rtPackDataAddon['data']['list'] as $addon) {
                            if (!empty($addon['list_data']) && count($addon['list_data'])) {
                                foreach ($addon['list_data'] as $add) {
                                    $arrAddUsed[] = $add['pack_code'];
                                }
                            }
                        }
                    }
                    //bỏ lọc trùng các gói addon và loại bỏ các gói 4G
                    if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                        foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                            if (in_array($a['pack_code'], $arrAddUsed) || $a['is4G'] == 1) {
                                unset($rtPackDataNew['data']['list'][0]['list_data'][$k]);
                            }
                        }
                    }
                    $arrReturnData[1] = $rtPackData['data'];
                    $arrReturnData[2] = $rtPackDataNew['data'];
                    $arrReturnData[3] = $rtPackDataAddon['data'];
                    $arrReturnData[4] = $rtPackDataPlus['data'];
                    $listArr4G = array(
                        0 => array(
                            'list_data' => $arrData4G
                        )
                    );
                    $arr4G = array(
                        'type' => $i18N->__('data'),
                        'name' => $i18N->__('Gói cước 4G'),
                        'list' => $listArr4G
                    );
                    $arrReturnData[0] = $arr4G;
                    $arrReturn = null;
                    $arrCode = array();
                    if (count($arrReturnData)) {
                        foreach ($arrReturnData as $val1) {
                            if (isset($val1['list']) && count($val1['list'])) {
                                foreach ($val1['list'] as $val2) {
                                    if (isset($val2['list_data']) && count($val2['list_data'])) {
                                        foreach ($val2['list_data'] as $val3) {
                                            $arrCode[] = $val3['pack_code'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    for ($i = 0; $i < 5; $i++) {
                        $list = array();

                        if ($listAll) {
                            foreach ($arrReturnData[$i]['list'] as $item) {

                                if ($item['list_data'])
                                    $list = array_merge($list, $item['list_data']);
                                $arrCode[] = $item['list_data']['pack_code'];
                            }
                            if (!count($list)) {
                                $list = null;
                            }
                            $arrReturnData[$i]['list'] = $list;
                        }
                        $arrReturn[] = $arrReturnData[$i];

                    }
                }
                    break;
                default: {
                    $arrReturn = null;
                }
            }
        }

        $dataAvailablePackages = array();
        if ($type == 'data_all') {
            foreach ($arrReturn as $idx => $value) {
                if (!empty($value['list'])) {
                    foreach ($value['list'] as $idx1 => $value1) {
                        $dataAvailablePackages[] = strtoupper($value1['pack_code']);
                    }
                }
            }
        } elseif (!empty($arrReturn['list'])) {
            foreach ($arrReturn['list'] as $idx1 => $value1) {
                if (!empty($value1['list_data'])) {
                    foreach ($value1['list_data'] as $idx2 => $value2) {
                        if (!empty($value2['pack_code'])) {
                            $dataAvailablePackages[] = strtoupper($value2['pack_code']);
                        }
                    }
                }
            }
        }
        //Lay danh sach goi cuoc dang sử dụng
        $data3GNoGWClient = new data3GNoGWClient();
        $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));
        $client3G = new data3GNoGWClient();
        $reponseAddOn = $client3G->checkDataAddon($msisdn);
        $arrAddonUsed = array();
        if ($reponseAddOn) {
            $reponseAddOn = rtrim($reponseAddOn, "|");
            $arrAddonUsed = explode("|", $reponseAddOn);
        }

        //offer 3 goi tot nhat cho khach hang
        $arrNoData = array('MIMD', 'MIMAX0QT', 'I0', 'I.0', 'MI0');
        $arrData = array('MIMAX25', 'MIMAXCM', 'MIMAXBL', '3GQUETOI', 'VTTEEN1', 'VTTEEN2', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'CB60', 'MISV65', 'CAMAU', 'BACLIEU', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');
        $list1 = array('V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800', 'MIMAXCM,MIMAXBL', 'CB60', 'CAMAU,BACLIEU', 'MIMAX25', '3GQUETOI', 'VTTEEN1', 'VTTEEN2', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'MISV65', 'VTTVUI');
        $list2 = array('MIMAX25', 'MIMAXCM', 'MIMAXBL', '3GQUETOI', 'VTTEEN1', 'VTTEEN2', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'CB60', 'MISV65', 'CAMAU', 'BACLIEU', 'MIMAX1.5', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list3 = array('FB1N', 'YT1', 'FB7', 'FB30', 'YT30');
        $list4 = array('VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');
        $list5Mi = array('MIMAX90', 'MIMAX', 'MIMAX25', 'MIMAX35', 'MIMAXS', 'MIMAXSV');
        $list5Current = array('FB1N', 'YT1', 'FB7', 'MIMAX25', 'FB30', 'YT30', '3GQUETOI ', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE', 'MISV65', 'MIMAX.5', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list5 = array('VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list6 = array('V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');
        $list7Mi = array('DMAX', 'MIMAX25', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'MISV65', 'MIMAX1.5');
        $list7Current = array('FB1N', 'YT1', 'FB7', 'MIMAX25', 'FB30', 'YT30', '3GQUETOI', 'MIMAX35', 'MIMAXS', 'MIMAXSV', 'VTTRE', 'VTKHOE ', 'MISV65', 'MIMAX1.5', 'VTTVUI', 'V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'DMAX200', 'V560', 'V630', 'V700', 'V800');
        $list7 = array('V200', 'V250', 'V300S', 'C300', 'V330', 'V380', 'V420', 'V480', 'V560', 'V630', 'V700', 'V800');

        $items = array();
        $check = 0;
        $checkCase = true;
        //Trường hợp thuê bao chưa đăng ký gói data nào
        if (in_array($currentPakage, $arrNoData)) {
            $arrData = $list1;
            $checkCase = false;
        }
        //check goi cuoc thuoc list2 thi hien thi list2
        if (in_array($currentPakage, $list2) && $checkCase) {
            $arrData = $list2;
            $checkCase = false;
        }
        //neu khach hang dang su dung cac goi addon
        if (in_array($currentPakage, $arrNoData) && (in_array("FB1N", $arrAddonUsed) || in_array("YT1", $arrAddonUsed) || in_array("FB7", $arrAddonUsed) || in_array("FB30", $arrAddonUsed) || in_array("YT30", $arrAddonUsed) && $checkCase)) {
            $arrData = $list2;
            $checkCase = false;
        }
        //neu dang tham gia goi mimax90 hoac mimax
        if (in_array($currentPakage, $list5Mi) && (in_array("FB1N", $arrAddonUsed) || in_array("YT1", $arrAddonUsed) || in_array("FB7", $arrAddonUsed) || in_array("FB30", $arrAddonUsed) || in_array("YT30", $arrAddonUsed) || in_array("3GQUETOI", $arrAddonUsed) || in_array("VTTRE", $arrAddonUsed) || in_array("VTVUI", $arrAddonUsed) || in_array("VTKHOE", $arrAddonUsed)) && $checkCase) {
            $arrData = $list5;
            $checkCase = false;
        }
        //dang dung goi DMAX
        if ($currentPakage == 'DMAX' && $checkCase) {
            $arrData = $list6;
            $checkCase = false;
        }
        //check list 7
        if (in_array($currentPakage, $list7Mi) && $checkCase) {
            $arrData = $list6;
            $checkCase = false;
        }
        //lay ra 3 goi data tốt nhất cho khách hàng
        if (($key = array_search($currentPakage, $arrData)) !== false) {
            unset($arrData[$key]);
        }
        $arrCheck = array_intersect($arrData, $dataAvailablePackages);
        if (count($arrCheck)) {
            foreach ($arrCheck as $ar) {
                if (count($items < 3)) {
                    $items[] = $ar;
                    break;
                }
            }
        }
        //lay lai danh sach 3 goi data tot nhat va sap xep
        $arr191 = array();
        $arrItemSave = array();
        if (count($items)) {
            foreach ($items as $it) {
                //map voi danh sach cac goi data trong db de lay noi dung
                if (count($arrReturn)) {
                    foreach ($arrReturn as $data1) {
                        if (!empty($data1['list'])) {
                            foreach ($data1['list'] as $data2) {
                                if ($it == strtoupper($data2['pack_code']) && !in_array($it, $arrItemSave)) {
                                    $arr191[] = $data2;
                                    $arrItemSave[] = $it;
                                }

                            }
                        }
                    }
                }
            }
        }

        $result['errorCode'] = 0;
        $result['message'] = $i18N->__('Thành công');
        if (!empty($arrReturn)) {
            $result['data'] = $arrReturn;
        } else {
            $result['data'] = array();
        }

        if (!empty($arr191)) {
            $result['special'] = $arr191;
        } else {
            $result['special'] = array();
        }
        return $this->renderText(json_encode($result));
    }

    function checkPackage($pack, $type = false)
    {
        $arrType = [
            1 => 'InternetPackage.MobileInternet',
            2 => 'InternetPackage.Dcom',
            3 => 'InternetPackage.BuyData',
            4 => 'InternetPackage.Addon',
        ];
        if (!$type) {
            // check data
            $checkData = self::callSolrCheck($arrType[1], $pack);
            if ($checkData) {
                return true;
            }
            // check dcom
            $checkDcom = self::callSolrCheck($arrType[2], $pack);
            if ($checkDcom) {
                return true;
            }
        } else {
            $check = self::callSolrCheck($arrType[$type], $pack);
            // check addon va plus
            if ($check) {
                return true;
            }
        }
        return false;
    }

    function callSolrCheck($type, $pack)
    {
        $query = new SolrQuery();
        $query->setQuery('*:*');
        $query->addFilterQuery('type: ' . $type);
        $query->addFilterQuery('code:' . $pack . ' or posCode:' . $pack);
        $query->addSortField('code', SolrQuery::ORDER_ASC);
        $query->addField('id');
        $query->setRows(10000);
        $query->setStart(0);
        $query_response = solrClient(VtHelper::getDBSolrName())->query($query);
        $response = $query_response->getResponse();
        $numFound = $response->response->numFound;
        if ($numFound && $numFound > 0) {
            return true;
        }
        return false;
    }

    //lay ra danh sach cac goi tot nhat cho khach hang
    public function executeGetPromotionDataUssdV3(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $timer = call('Web.ActionLog.startTimer');
        $result = array();
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $telType = trim($request->getParameter('telType'));
        if (!empty($telType) and $telType == 'dcom') {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18N->__('Dịch vụ không áp dụng cho thuê bao.')
            );
            return $this->renderText(json_encode($arrReturn));
        }
        if (!$request->isMethod('POST')) {
            $arrReturn = array(
                'errorCode' => '1',
                'message' => $i18N->__('Phương thức không hợp lệ'),
            );
            return $this->renderText(json_encode($arrReturn));
        }
        $key = VtHelper::validateNullparams(array('type' => $type));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }

        //check thue bao kich hoat sau ngay 01/01/2016 thi khong hien thi chuong trinh khuyen mai nao
        $checkOff = MVPHelper::checkPromotionMsisdnOff($msisdn);
        if ($checkOff) {
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = array();
            $result['special'] = array();
            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }
            if (!empty($webservices)) {
                $logFields['webservices'] = json_encode($webservices);
            }
            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }

        $listAll = $request->getParameter('list_all');
        $listDataUssd = sfConfig::get('app_list_data_ussd');
        $arrReturn = null;
        $message = null;
        // data
        $client = new WsDataClient();
        $strPackReg = $client->checkData3gV2($msisdn);
        $arrListReg = array();
        $listDataDb = array();
        if ($strPackReg) {
            $arrListReg = explode('|', strtoupper($strPackReg));
        }

        $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.MobileInternet'
        ));
        $listDataDbDcom = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Dcom'
        ));
        if (!empty($listDataDbMi)) {
            foreach ($listDataDbMi as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        if (!empty($listDataDbDcom)) {
            foreach ($listDataDbDcom as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        // end data
        // addon
        $strPackRegAddon = $client->checkDataAddon($msisdn);
        if ($strPackRegAddon) {
            $arrListReg = array_merge($arrListReg, explode('|', strtoupper($strPackRegAddon)));
        }
        $listDataDbAddon = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Addon'
        ));
        //lưu danh sách mã các gói addon
        $arrCodeAddon = array();
        $allAddonDb = array();
        if (!empty($listDataDbAddon)) {
            foreach ($listDataDbAddon as $key => $itemDB) {
                if ($itemDB['status'] == 1) {
                    $arrCodeAddon[] = $itemDB['code'];
                }
                $allAddonDb[strtolower($key)] = $itemDB;
            }
        }
        $arrListRegNew = array();
        foreach ($arrListReg as $itemReg) {
            if (!empty($listDataDb[strtolower($itemReg)])) {
                $dataItemReg = $listDataDb[strtolower($itemReg)];
                $arrListRegNew[] = !empty($dataItemReg['code']) ? $dataItemReg['code'] : '';
            } else {
                $arrListRegNew[] = $itemReg;
            }
        }
        $arrListReg = $arrListRegNew;
        if ($type) {
            switch ($type) {
                case 'data': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $arrReturn = $rtPackData['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_new': {
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $arrReturn = $rtPackDataNew['data'];
                }
                    break;
                case 'data_addon': {
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $arrReturn = $rtPackDataAddon['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_plus': {
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturn = $rtPackDataPlus['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_all': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $rtPackDataAddon = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $allAddonDb, $arrListReg, $type = 'data_addon');
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    //lấy ra các gói 4G
                    $arrData4G = array();
                    if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                        foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                            //lấy ra gói 4G
                            if ($a['is4G'] == 1) {
                                $arrData4G[] = $a;
                            }
                        }
                    }
                    //lay danh sach cac goi addon dang duoc khuyen mai
                    $arrAddUsed = array();
                    if (count($rtPackDataAddon['data']['list'])) {
                        foreach ($rtPackDataAddon['data']['list'] as $addon) {
                            if (!empty($addon['list_data']) && count($addon['list_data'])) {
                                foreach ($addon['list_data'] as $add) {
                                    $arrAddUsed[] = $add['pack_code'];
                                }
                            }
                        }
                    }
                    //bỏ lọc trùng các gói addon và loại bỏ các gói 4G
                    if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                        foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                            if (in_array($a['pack_code'], $arrAddUsed) || $a['is4G'] == 1) {
                                unset($rtPackDataNew['data']['list'][0]['list_data'][$k]);
                            }
                        }
                    }
                    $rtPackDataNew['data']['name'] = $i18N->__("Gói cước 3G");
//                    $arrReturnData[1] = $rtPackData['data'];
                    $arrReturnData[1] = $rtPackDataNew['data'];
                    $arrReturnData[2] = $rtPackDataAddon['data'];
                    $arrReturnData[3] = $rtPackDataPlus['data'];
                    $listArr4G = array(
                        0 => array(
                            'list_data' => $arrData4G
                        )
                    );
                    $arr4G = array(
                        'type' => 'data',
                        'name' => $i18N->__('Gói cước 4G'),
                        'list' => $listArr4G
                    );
                    $arrReturnData[0] = $arr4G;
                    $arrReturn = null;
                    $arrCode = array();
                    if (count($arrReturnData)) {
                        foreach ($arrReturnData as $val1) {
                            if (isset($val1['list']) && count($val1['list'])) {
                                foreach ($val1['list'] as $val2) {
                                    if (isset($val2['list_data']) && count($val2['list_data'])) {
                                        foreach ($val2['list_data'] as $val3) {
                                            $arrCode[] = $val3['pack_code'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    for ($i = 0; $i < 4; $i++) {
                        $list = array();

                        if ($listAll) {
                            foreach ($arrReturnData[$i]['list'] as $item) {

                                if ($item['list_data'])
                                    $list = array_merge($list, $item['list_data']);
                                $arrCode[] = $item['list_data']['pack_code'];
                            }
                            if (!count($list)) {
                                $list = null;
                            }
                            $arrReturnData[$i]['list'] = $list;
                        }
                        $arrReturn[] = $arrReturnData[$i];

                    }
                }
                    break;
                default: {
                    $arrReturn = null;
                }
            }
        }

        $dataAvailablePackages = array();
        if ($type == 'data_all') {
            foreach ($arrReturn as $idx => $value) {
                if (!empty($value['list'])) {
                    foreach ($value['list'] as $idx1 => $value1) {
                        $dataAvailablePackages[] = strtoupper($value1['pack_code']);
                    }
                }
            }
        } elseif (!empty($arrReturn['list'])) {
            foreach ($arrReturn['list'] as $idx1 => $value1) {
                if (!empty($value1['list_data'])) {
                    foreach ($value1['list_data'] as $idx2 => $value2) {
                        if (!empty($value2['pack_code'])) {
                            $dataAvailablePackages[] = strtoupper($value2['pack_code']);
                        }
                    }
                }
            }
        }
        //Lay danh sach goi cuoc dang sử dụng
        $data3GNoGWClient = new data3GNoGWClient();
        $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));
        $client3G = new data3GNoGWClient();
        $reponseAddOn = $client3G->checkDataAddon($msisdn);
        $arrAddonUsed = array();
        if ($reponseAddOn) {
            $reponseAddOn = rtrim($reponseAddOn, "|");
            $arrAddonUsed = explode("|", $reponseAddOn);
        }


        $items = array();
        //lay danh sach goi offer
        $offerClient = new Offer();
        $offerCodes = $offerClient->getUssdTopOffer($msisdn);
        //lay lai danh sach 3 goi data tot nhat va sap xep
        $arr191 = array();
        $arrItemSave = array();
        if (count($offerCodes)) {
            foreach ($offerCodes as $it) {
                //map voi danh sach cac goi data trong db de lay noi dung
                if (count($arrReturn)) {
                    foreach ($arrReturn as $data1) {
                        if (!empty($data1['list'])) {
                            foreach ($data1['list'] as $data2) {
                                if ($it == strtoupper($data2['pack_code']) && !in_array($it, $arrItemSave)) {
                                    $arr191[] = $data2;
                                    $arrItemSave[] = $it;
                                }

                            }
                        }
                    }
                }
            }
        }

        $result['errorCode'] = 0;
        $result['message'] = $i18N->__('Thành công');
        if (!empty($arrReturn)) {
            $result['data'] = $arrReturn;
        } else {
            $result['data'] = array();
        }

        if (!empty($arr191)) {
            $result['special'] = $arr191;
        } else {
            $result['special'] = array();
        }
        return $this->renderText(json_encode($result));
    }


    /**
     * huync2: xhhbh
     * @param sfWebRequest $request
     * @return sfView
     * @throws sfConfigurationException
     */
    public function executeGetPromotionDataUssdV4(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $timer = call('Web.ActionLog.startTimer');
        $result = array();
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $telType = trim($request->getParameter('telType'));
        if (!empty($telType) and $telType == 'dcom') {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $i18N->__('Dịch vụ không áp dụng cho thuê bao.')
            );
            return $this->renderText(json_encode($arrReturn));
        }
        if (!$request->isMethod('POST')) {
            $arrReturn = array(
                'errorCode' => '1',
                'message' => $i18N->__('Phương thức không hợp lệ'),
            );
            return $this->renderText(json_encode($arrReturn));
        }
        $key = VtHelper::validateNullparams(array('type' => $type));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }

        //check thue bao kich hoat sau ngay 01/01/2016 thi khong hien thi chuong trinh khuyen mai nao
        $checkOff = MVPHelper::checkPromotionMsisdnOff($msisdn);
        if ($checkOff) {
            $result['errorCode'] = 0;
            $result['message'] = $i18N->__('Thành công');
            $result['data'] = array();
            $result['special'] = array();
            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }
            if (!empty($webservices)) {
                $logFields['webservices'] = json_encode($webservices);
            }
            call('Web.ActionLog.startTimer', array('timer' => $timer));
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }

        $listAll = $request->getParameter('list_all');
        $listDataUssd = sfConfig::get('app_list_data_ussd');
        $arrReturn = null;
        $message = null;
        // data
        $client = new WsDataClient();
        $strPackReg = $client->checkData3gV2($msisdn);
        $arrListReg = array();
        $listDataDb = array();
        if ($strPackReg) {
            $arrListReg = explode('|', strtoupper($strPackReg));
        }

        $listDataDbMi = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.MobileInternet'
        ));
        $listDataDbDcom = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Dcom'
        ));
        if (!empty($listDataDbMi)) {
            foreach ($listDataDbMi as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        if (!empty($listDataDbDcom)) {
            foreach ($listDataDbDcom as $key => $itemDB) {
                $listDataDb[strtolower($key)] = $itemDB;
            }
        }
        // end data
        // addon
        $strPackRegAddon = $client->checkDataAddon($msisdn);
        if ($strPackRegAddon) {
            $arrListReg = array_merge($arrListReg, explode('|', strtoupper($strPackRegAddon)));
        }
        $listDataDbAddon = call('App.Viettel.InternetPackage.getListAllPackage', array(
            'type' => 'InternetPackage.Addon'
        ));
        //lưu danh sách mã các gói addon
        $arrCodeAddon = array();
        $allAddonDb = array();
        if (!empty($listDataDbAddon)) {
            foreach ($listDataDbAddon as $key => $itemDB) {
                if ($itemDB['status'] == 1) {
                    $arrCodeAddon[] = $itemDB['code'];
                }
                $allAddonDb[strtolower($key)] = $itemDB;
            }
        }
        $arrListRegNew = array();
        foreach ($arrListReg as $itemReg) {
            if (!empty($listDataDb[strtolower($itemReg)])) {
                $dataItemReg = $listDataDb[strtolower($itemReg)];
                $arrListRegNew[] = !empty($dataItemReg['code']) ? $dataItemReg['code'] : '';
            } else {
                $arrListRegNew[] = $itemReg;
            }
        }
        $arrListReg = $arrListRegNew;
        if ($type) {
            switch ($type) {
                case 'data': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $arrReturn = $rtPackData['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_new': {
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $arrReturn = $rtPackDataNew['data'];
                }
                    break;
                case 'data_addon': {
                    $rtPackDataAddon = self::returnPackDataAddon($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $arrReturn = $rtPackDataAddon['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_plus': {
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    $arrReturn = $rtPackDataPlus['data'];
                    $list = array();
                    if ($listAll) {
                        foreach ($arrReturn['list'] as $item) {
                            if ($item['list_data'])
                                $list = array_merge($list, $item['list_data']);
                        }
                        if (!count($list)) {
                            $list = null;
                        }
                        $arrReturn['list'] = $list;
                    }
                }
                    break;
                case 'data_all': {
                    $rtPackData = self::returnPackData($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackData = $rtPackData['arrReturn'];
                    $rtPackDataNew = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $listDataDb, $arrListReg);
                    $rtPackDataNew = $rtPackDataNew['arrReturn'];
                    $rtPackDataAddon = self::returnPackDataNew($listDataUssd, $msisdn, $listAll, $allAddonDb, $arrListReg, 'data_addon');
                    $rtPackDataAddon = $rtPackDataAddon['arrReturn'];
                    $rtPackDataPlus = self::returnPackDataPlus($listDataUssd, $msisdn, $listAll);
                    $rtPackDataPlus = $rtPackDataPlus['arrReturn'];
                    //lấy ra các gói 4G
                    $arrData4G = array();
                    if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                        foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                            //lấy ra gói 4G
                            if ($a['is4G'] == 1) {
                                $arrData4G[] = $a;
                            }
                        }
                    }
                    //lay danh sach cac goi addon dang duoc khuyen mai
                    $arrAddUsed = array();
                    if (count($rtPackDataAddon['data']['list'])) {
                        foreach ($rtPackDataAddon['data']['list'] as $addon) {
                            if (!empty($addon['list_data']) && count($addon['list_data'])) {
                                foreach ($addon['list_data'] as $add) {
                                    $arrAddUsed[] = $add['pack_code'];
                                }
                            }
                        }
                    }
                    //bỏ lọc trùng các gói addon và loại bỏ các gói 4G
                    if (!empty($rtPackDataNew['data']['list'][0]['list_data']) && count($rtPackDataNew['data']['list'][0]['list_data'])) {
                        foreach ($rtPackDataNew['data']['list'][0]['list_data'] as $k => $a) {
                            if (in_array($a['pack_code'], $arrAddUsed) || $a['is4G'] == 1) {
                                unset($rtPackDataNew['data']['list'][0]['list_data'][$k]);
                            }
                        }
                    }
                    $rtPackDataNew['data']['name'] = $i18N->__("Gói cước 3G");
//                    $arrReturnData[1] = $rtPackData['data'];
                    $arrReturnData[1] = $rtPackDataNew['data'];
                    $arrReturnData[2] = $rtPackDataAddon['data'];
                    $arrReturnData[3] = $rtPackDataPlus['data'];
                    $listArr4G = array(
                        0 => array(
                            'list_data' => $arrData4G
                        )
                    );
                    $arr4G = array(
                        'type' => 'data',
                        'name' => $i18N->__('Gói cước 4G'),
                        'list' => $listArr4G
                    );
                    $arrReturnData[0] = $arr4G;
                    $arrReturn = null;
                    $arrCode = array();
                    if (count($arrReturnData)) {
                        foreach ($arrReturnData as $val1) {
                            if (isset($val1['list']) && count($val1['list'])) {
                                foreach ($val1['list'] as $val2) {
                                    if (isset($val2['list_data']) && count($val2['list_data'])) {
                                        foreach ($val2['list_data'] as $val3) {
                                            $arrCode[] = $val3['pack_code'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    for ($i = 0; $i < 4; $i++) {
                        $list = array();

                        if ($listAll) {
                            foreach ($arrReturnData[$i]['list'] as $item) {

                                if ($item['list_data'])
                                    $list = array_merge($list, $item['list_data']);
                                $arrCode[] = $item['list_data']['pack_code'];
                            }
                            if (!count($list)) {
                                $list = null;
                            }
                            $arrReturnData[$i]['list'] = $list;
                        }
                        $arrReturn[] = $arrReturnData[$i];

                    }
                }
                    break;
                default: {
                    $arrReturn = null;
                }
            }
        }

        $dataAvailablePackages = array();
        if ($type == 'data_all') {
            foreach ($arrReturn as $idx => $value) {
                if (!empty($value['list'])) {
                    foreach ($value['list'] as $idx1 => $value1) {
                        foreach ($value1['list_data'] as $idx2 => $value2) {
                            $dataAvailablePackages[] = strtoupper($value2['pack_code']);
                        }
                    }
                }
            }
        } elseif (!empty($arrReturn['list'])) {
            foreach ($arrReturn['list'] as $idx1 => $value1) {
                if (!empty($value1['list_data'])) {
                    foreach ($value1['list_data'] as $idx2 => $value2) {
                        if (!empty($value2['pack_code'])) {
                            $dataAvailablePackages[] = strtoupper($value2['pack_code']);
                        }
                    }
                }
            }
        }
        //Lay danh sach goi cuoc dang sử dụng
        $data3GNoGWClient = new data3GNoGWClient();
        $currentPakage = ApiHelper::convertPosPack($data3GNoGWClient->checkData3gV2($msisdn));
        $client3G = new data3GNoGWClient();
        $reponseAddOn = $client3G->checkDataAddon($msisdn);
        $arrAddonUsed = array();
        if ($reponseAddOn) {
            $reponseAddOn = rtrim($reponseAddOn, "|");
            $arrAddonUsed = explode("|", $reponseAddOn);
        }


        $items = array();

        //lay danh sach goi offer
        $offerClient = new Offer();
        $offerCodes = $offerClient->getUssdTopOffer($msisdn);
        //lay lai danh sach 3 goi data tot nhat va sap xep
        $arr191 = array();
        $arrItemSave = array();
        if (count($offerCodes)) {
            foreach ($offerCodes as $it) {
                //map voi danh sach cac goi data trong db de lay noi dung
                if (count($arrReturn)) {
                    foreach ($arrReturn as $data1) {
                        if (!empty($data1['list'])) {
                            foreach ($data1['list'] as $data3) {
                                foreach ($data3['list_data'] as $data2) {
                                    if (strtoupper($it) == strtoupper($data2['pack_code']) && !in_array($it, $arrItemSave)) {
                                        $arr191[] = $data2;
                                        $arrItemSave[] = $it;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $result['errorCode'] = 0;
        $result['message'] = $i18N->__('Thành công');
        if (!empty($arrReturn)) {
            $result['data'] = $arrReturn;
        } else {
            $result['data'] = array();
        }

        if (!empty($arr191)) {
            $result['special'] = $arr191;
        } else {
            $result['special'] = array();
        }
        return $this->renderText(json_encode($result));
    }


    public function executeRegisterMIV2(sfWebRequest $request)
    {
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'RegisterData',
            'title' => $i18N->__('Đăng ký gói cước MI'),
            'service' => 'apiv2.mobileInternet.executeRegisterMIV2',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            $serviceCode = $request->getParameter('service_code', null);

            # huync2: kiem tra goi Data trong DB truoc khi dang ky
            $checkData = self::checkPackage(trim($serviceCode));
            if ($checkData == false) {
                sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterMI goi khong hop le: ' . $serviceCode . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                $arrReturn = ApiHelper::formatResponse(
                    ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                );

                if (!empty($arrReturn)) {
                    $logFields['results'] = json_encode($arrReturn);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($arrReturn));
            }

            $logFields['inputs'] = json_encode($request);
            if ($serviceCode == null) {
                $result['errorCode'] = 3;
                $result['message'] = $i18N->__('Gói cước không đúng, vui lòng thử lại!'); //Truyền thiếu tham số service_code
                $result['data'] = null;

                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
            //nguyetNT32: chặn 4 gói 0 mất tiền
            $arrPackege = array('MIMIN', 'MI0', 'MIMAX0', 'DC0');
            if (in_array(strtoupper($serviceCode), $arrPackege)) {
                $result['errorCode'] = 3;
                $result['message'] = $i18N->__('Gói cước này hiện không được hủy trên My Viettel, vui lòng thử lại sau!'); //Truyền thiếu tham số service_code
                $result['data'] = null;

                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));


                return $this->renderText(json_encode($result));
            }
            $client = new MobileInternetClient();

            $current_pakage = $client->checkMI($msisdn);
            if ($current_pakage == $serviceCode) {
                $result['errorCode'] = 2;
                $result['message'] = $i18N->__('Đăng ký thất bại do Quý khách đang sử dụng gói cước %package%!', array('%package%' => $current_pakage));
                $result['data'] = null;

                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));


                return $this->renderText(json_encode($result));
            }

            call('Web.ActionLog.startTimer');
            $response = $client->registerMI($msisdn, $serviceCode);
            if ($response) {
                $result['errorCode'] = 0;
                $result['message'] = $i18N->__('Quý khách đã đăng ký thành công gói ' . $serviceCode);
                // goi API ghi nhan xhhbh
                $xhhbh = XhhbnHelper::purchase($serviceCode, $msisdn, self::TYPE_GET_PROMOTION_DATA_USSD);
                if ($xhhbh) {
                    $objXhhbh = json_decode($xhhbh);
                    if ($objXhhbh->errorCode == '0') {
                        $objXhhbh->errorCode = ApiResponseCode::SUCCESS;
                        $objXhhbh->message = $i18N->__('Quý khách đã đăng ký thành công gói ' . $serviceCode);

                        if (!empty($result)) {
                            $logFields['results'] = json_encode($result);
                        }
                        if (!empty($webservices)) {
                            $logFields['webservices'] = json_encode($objXhhbh);
                        }
                        call('Web.ActionLog.startTimer', array('timer' => $timer));
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($objXhhbh));
                    }
                }

                //$result['message'] = $client->getErrorMessage();
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }
                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($result));
            } else {
                $result['errorCode'] = 2;
                $result['message'] = $client->getErrorMessage(); //$i18N->__('Có lỗi xảy ra trong quá trình kết nối. Quý khách vui lòng thử lại sau!');
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }
                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($result));
            }
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    public function executeBuyDataV2(sfWebRequest $request)
    {
        $i18N = $this->getContext()->getI18N();
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'RegisterData',
            'title' => $i18N->__('Mua data'),
            'service' => 'apiv2.mobileInternet.executeBuyDataV2',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        if ($request->isMethod('POST')) {
            $msisdn = user()->id;
            $package = $request->getParameter('package_name', null);

            $logFields['inputs'] = json_encode($request);
            if ($package == null) {
                $result['errorCode'] = 3;
                $result['message'] = $i18N->__('Truyền thiếu tham số package_name');
                $result['data'] = null;

                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }

                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
            $client = new dataPlusClient();
            $response = $client->DataPlusBuy($msisdn, $package);
            if ($response) {
                $result['errorCode'] = 0;
                $result['message'] = $i18N->__('Mua thêm data thành công');

                // goi API ghi nhan xhhbh
                $xhhbh = XhhbnHelper::purchase($package, $msisdn, self::TYPE_GET_PROMOTION_DATA_USSD_PLUS);
                if ($xhhbh) {
                    $objXhhbh = json_decode($xhhbh);
                    if ($objXhhbh->errorCode == '0') {
                        $objXhhbh->errorCode = ApiResponseCode::SUCCESS;
                        $objXhhbh->message = $i18N->__('Mua thêm data thành công');

                        if (!empty($result)) {
                            $logFields['results'] = json_encode($result);
                        }
                        if (!empty($webservices)) {
                            $logFields['webservices'] = json_encode($objXhhbh);
                        }
                        call('Web.ActionLog.startTimer', array('timer' => $timer));
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($objXhhbh));
                    }
                }

                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }
                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));
                return $this->renderText(json_encode($result));
            } else {
                $result['errorCode'] = 2;
                call('Web.ActionLog.startTimer');
                $result['message'] = $client->getErrorMessage();
                $webservices[] = call('Web.ActionLog.getWserviceLog', array('wsCode' => 'dataPlusClient:getErrorMessage'));
                $result['data'] = null;
                if (!empty($result)) {
                    $logFields['results'] = json_encode($result);
                }
                if (!empty($webservices)) {
                    $logFields['webservices'] = json_encode($webservices);
                }
                call('Web.ActionLog.startTimer', array('timer' => $timer));
                call('Web.ActionLog.edit', array(
                    'fields' => $logFields,
                ));

                return $this->renderText(json_encode($result));
            }
        } else {
            $result['errorCode'] = 1;
            $result['message'] = $i18N->__('Sai phương thức HTTP');
            $result['data'] = null;
            return $this->renderText(json_encode($result));
        }
    }

    public function executeRegisterDataUssdV2(sfWebRequest $request)
    {
        $timer = call('Web.ActionLog.startTimer');
        $webservices = array();
        $i18N = $this->getContext()->getI18N();
        $inputs = $_REQUEST;
        unset($inputs['actionName']);
        $result = array();
        $logFields = array(
            'logType' => 'Data',
            'actionType' => 'RegisterData',
            'title' => $i18N->__('Đăng ký data ussd'),
            'service' => 'apiv2.mobileInternet.executeRegisterDataUssdV2',
            'creatorId' => user()->id,
            'objectId' => !empty(user()->id) ? user()->id : '',
            'objectTitle' => !empty(user()->fullName) ? user()->fullName : (!empty(user()->msisdn) ? user()->msisdn : user()->id),
            'webservices' => json_encode($webservices),
            'inputs' => $inputs,
            'content' => '',
            'results' => json_encode($result),
        );
        $msisdn = user()->msisdn;
        $type = $request->getParameter('type');
        $package = $request->getParameter('package');
        $key = VtHelper::validateNullparams(array('type' => $type, 'package' => $package));
        if ($key) {
            $result['errorCode'] = 2;
            $result['message'] = $i18N->__('%key% không được để trống', array('%key%' => $key));
            $result['data'] = null;
            if (!empty($result)) {
                $logFields['results'] = json_encode($result);
            }
            call('Web.ActionLog.edit', array(
                'fields' => $logFields,
            ));
            return $this->renderText(json_encode($result));
        }
        $client = false;
        $socialType = self::TYPE_GET_PROMOTION_DATA_USSD;
        if ($type) {
            switch ($type) {
                case 'data': {
                    $client = new MobileInternetClient();
                    $current_pakage = $client->checkMI($msisdn);
                    if ($current_pakage == $package) {
                        $result['errorCode'] = 2;
                        $result['message'] = $i18N->__('Đăng ký thất bại do Quý khách đang sử dụng gói cước %package%!', array('%package%' => $current_pakage));
                        $result['data'] = null;
                        if (!empty($result)) {
                            $logFields['results'] = json_encode($result);
                        }
                        if (!empty($webservices)) {
                            $logFields['webservices'] = json_encode($webservices);
                        }
                        call('Web.ActionLog.startTimer', array('timer' => $timer));
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($result));
                    }
                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    $checkData = self::checkPackage($package);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );

                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }
                    $result = $client->registerMI($msisdn, $package);

                }
                    break;
                case 'data_new': {
                    $client = new WsDataClient();
                    $listDataAddon = self::getListPackDataAddon($msisdn);
                    if (!empty($listDataAddon['webservices'])) {
                        $webservices = array_merge($webservices, $listDataAddon['webservices']);
                    }
                    $listDataAddon = $listDataAddon['arrPackage'];
                    $item = !empty($listDataAddon[strtolower($package)]) ? $listDataAddon[strtolower($package)] : '';
                    if ($item['type'] == '1') {
                        $client = new MobileInternetClient();
                        # huync2: kiem tra goi Data trong DB truoc khi dang ky
//        $checkData = Data('InternetPackage')->useIndex('code')->select(array('code' => $package));
                        $checkData = self::checkPackage($package);
                        if ($checkData == false) {
                            sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                            $arrReturn = ApiHelper::formatResponse(
                                ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                            );

                            if (!empty($arrReturn)) {
                                $logFields['results'] = json_encode($arrReturn);
                            }
                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($arrReturn));
                        }
                        $result = $client->registerMI($msisdn, $package);
                        $socialType = self::TYPE_GET_PROMOTION_DATA_USSD;
//                        $result = $client->registerData3gV2($msisdn, $package);
                    } elseif ($item['type'] == '0') {
                        # huync2: kiem tra goi Data trong DB truoc khi dang ky
//        $checkData = Data('InternetPackage')->useIndex('code')->select(array('code' => $package));
                        $checkData = self::checkPackage($package, 4);
                        if ($checkData == false) {
                            sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                            $arrReturn = ApiHelper::formatResponse(
                                ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                            );
                            if (!empty($arrReturn)) {
                                $logFields['results'] = json_encode($arrReturn);
                            }
                            call('Web.ActionLog.edit', array(
                                'fields' => $logFields,
                            ));
                            return $this->renderText(json_encode($arrReturn));
                        }

                        $socialType = self::TYPE_GET_PROMOTION_DATA_USSD_ADDON;
                        $result = $client->registerAddOn($msisdn, $package);
                    } else {
                        $result = false;
                    }

                }
                    break;
                case 'data_addon': {
                    $client = new WsDataClient();

                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
//        $checkData = Data('InternetPackage')->useIndex('code')->select(array('code' => $package));
                    $checkData = self::checkPackage($package, 4);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );
                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }

                    $socialType = self::TYPE_GET_PROMOTION_DATA_USSD_ADDON;
                    $result = $client->registerAddOn($msisdn, $package);
                }
                    break;
                case 'data_plus': {
                    $client = new WsDataPlusClient();

                    # huync2: kiem tra goi Data trong DB truoc khi dang ky
                    // $checkData = Data('InternetPackage')->useIndex('code')->select(array('code' => $package));
                    $checkData = self::checkPackage($package, 3);
                    if ($checkData == false) {
                        sfContext::getInstance()->getLogger()->log('checkRegData apiv2.mobileInternet.executeRegisterDataUssd goi khong hop le: ' . $package . '|' . user()->msisdn . '|' . json_encode(VtHelper::getDeviceIp()), sfLogger::ERR);
                        $arrReturn = ApiHelper::formatResponse(
                            ApiResponseCode::ERROR, null, $i18N->__('Gói cước không hợp lệ.')
                        );
                        if (!empty($arrReturn)) {
                            $logFields['results'] = json_encode($arrReturn);
                        }
                        call('Web.ActionLog.edit', array(
                            'fields' => $logFields,
                        ));
                        return $this->renderText(json_encode($arrReturn));
                    }
                    $result = $client->BuyData($msisdn, $package);
                    $socialType = self::TYPE_GET_PROMOTION_DATA_USSD_PLUS;
                }
                    break;
                default: {
                    $result = false;
                }
            }
        }
        $message = '';
        if ($client) {
            $message = $client->getErrorMessage();
        }
        if ($result) {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::SUCCESS, null, $message
            );
            // goi API ghi nhan xhhbh
            $xhhbh = XhhbnHelper::purchase($package, $msisdn, $socialType);
            if ($xhhbh) {
                $objXhhbh = json_decode($xhhbh);
                if ($objXhhbh->errorCode == '0') {
                    $objXhhbh->errorCode = ApiResponseCode::SUCCESS;
                    $objXhhbh->message = $message;

                    if (!empty($arrReturn)) {
                        $logFields['results'] = json_encode($arrReturn);
                    }

                    if (!empty($webservices)) {
                        $logFields['webservices'] = json_encode($objXhhbh);
                    }
                    call('Web.ActionLog.startTimer', array('timer' => $timer));
                    call('Web.ActionLog.edit', array(
                        'fields' => $logFields,
                    ));
                    return $this->renderText(json_encode($objXhhbh));
                }
            }
        } else {
            $arrReturn = ApiHelper::formatResponse(
                ApiResponseCode::ERROR, null, $message
            );
        }
        if (!empty($arrReturn)) {
            $logFields['results'] = json_encode($arrReturn);
        }
        if (!empty($webservices)) {
            $logFields['webservices'] = json_encode($webservices);
        }
        call('Web.ActionLog.startTimer', array('timer' => $timer));
        call('Web.ActionLog.edit', array(
            'fields' => $logFields,
        ));
        return $this->renderText(json_encode($arrReturn));
    }

    public function replaceStringTags($str)
    {
        $i18n = $this->getContext()->getI18N();
        if (!is_object($str)) {
            $str = str_replace('DAY', $i18n->__('NGÀY'), $str);
            $str = str_replace('WEEK', $i18n->__('TUẦN'), $str);
            $str = str_replace('MONTH', $i18n->__('THÁNG'), $str);
        } else {
            $str = '';
        }

        return $str;
    }
}
