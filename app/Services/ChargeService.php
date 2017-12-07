<?php

namespace App\Services;

use App\Libs\WxApi;
use App\Models\ChargeTasks;
use App\Models\DeviceInfo;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ChargeService extends BaseService
{

    /**
     * 是否正在充电
     */
    public static function isCharging()
    {

    }

    /**
     * 开始充电
     * @param $userId
     * @param $deviceNo
     * @param $portNo
     * @param $mode 0=充满（小时）
     */
    public static function startCharge($userId, $deviceId, $mode, $formId)
    {
        $duration = $mode * 3600;
        if (!$deviceModel = DeviceInfo::find($deviceId)) {
            Log::warning('deviceInfo not find deviceId:' . $deviceId);
            return false;
        }
        $deviceNo = $deviceModel->device_no;
        $portNo = $deviceModel->port_no;
        $chargeTaskMod = new ChargeTasks();
        $taskId = $chargeTaskMod->createTask($userId, $deviceNo, $portNo, $duration, $formId);
        if(!$taskId){
            return false;
        }
        return self::sendCmdToStartCharge($deviceNo, $portNo, $taskId);
    }

    public static function sendCmdToStartCharge($deviceNo, $portNo, $taskId)
    {
        Log::debug("start charge $deviceNo $portNo $taskId");
        DeviceService::sendChargingHash($deviceNo, $portNo, $taskId);
        return CommandService::sendCommandChargeStart($deviceNo, $portNo);
    }

    public static function beginCharingByTaskId($taskId)
    {
        $model = ChargeTasks::find($taskId);
        if($model && $model->task_state == ChargeTasks::TASK_STATE_INIT){
            $model->task_state = ChargeTasks::TASK_STATE_CHARGING;
            $model->begin_at = date('Y-m-d H:i:s');
            return $model->save();
        }elseif($model){
            return true;
        }
        return false;
    }

    /**
     * 结束充电
     * @param $device array|string    ['device_no'=>'123','port_no'=>'1']|$deviceId
     * @param int $state
     * @return bool
     */
    public static function endCharge($device, $state = ChargeTasks::TASK_STATE_COMPLETE, $sendCmd = true)
    {
        if (is_string($device)) {
            if (!$deviceModel = DeviceInfo::find($device)) {
                Log::warning('deviceInfo not find deviceId:' . $device);
                return false;
            }
            $deviceNo = $deviceModel->device_no;
            $portNo = $deviceModel->port_no;
        } elseif (is_array($device)) {
            $deviceNo = $device['device_no'];
            $portNo = $device['port_no'];
        } else {
            Log::error('device is not array|string');
            return false;
        }

        $model = ChargeTasks::where(['device_no' => $deviceNo, 'port_no' => $portNo])->orderBy('id', 'desc')->first();
        if (!$model) {
            return false;
        }
        $begin = $model->begin_at;
        $beginTime = strtotime($begin);
        $model->actual_time = time() - $beginTime;
        $model->task_state = $state;
        //此处预留扣费逻辑
        $model->save();
        if ($sendCmd) {
            CommandService::sendCommandChargeEnd($deviceNo, $portNo);
        }
    }

    /**
     * @param $deviceId
     * @return bool
     */
    public static function endChargeByUser($deviceId)
    {
        return self::endCharge($deviceId, ChargeTasks::TASK_STATE_USER_END);
    }

    /**
     * 时间结束自动中断停电
     * @param $deviceNo
     * @param $portNo
     * @return bool
     */
    public static function endChargeByTimeOver($deviceNo, $portNo)
    {
        return self::endCharge(['device_no' => $deviceNo, 'port_no' => $portNo], ChargeTasks::TASK_STATE_TIME_END);
    }

    /**
     * @param $deviceNo
     * @param $portNo
     * @return bool
     */
    public static function chargeHaltComplete($deviceNo, $portNo)
    {
        $device = ['device_no' => $deviceNo, 'port_no' => $portNo];
        $state = ChargeTasks::TASK_STATE_COMPLETE;
        $taskId = ChargeTasks::getLastTaskIdByDevice($deviceNo, $portNo);
        self::sendEndMessage($taskId);
        return self::endCharge($device, $state, true);
    }

    /**
     * @param $deviceNo
     * @param $portNo
     * @return bool
     */
    public static function chargeHaltAbnormal($deviceNo, $portNo)
    {
        $device = ['device_no' => $deviceNo, 'port_no' => $portNo];
        $state = ChargeTasks::TASK_STATE_END_ABMORMAL;
        $taskId = ChargeTasks::getLastTaskIdByDevice($deviceNo, $portNo);
        self::sendEndAbMessage($taskId);
        return self::endCharge($device, $state, true);
    }

    /**
     * 获取当前充电任务信息
     * @param $userId
     * @return array|bool
     */
    public static function getLastTaskInfo($userId)
    {
        $data = [
            'status' => 0,//正在冲
            'mins' => 0,//分钟
        ];
        $model = ChargeTasks::whereUserId($userId)->orderByDesc('id')->first();
        if (!$model) {
            return false;
        }

        if ($model->task_state == ChargeTasks::TASK_STATE_END_ABMORMAL) {
            $data['status'] = 1;//异常终止
        } elseif ($model->task_state == ChargeTasks::TASK_STATE_TIME_END || $model->task_state == ChargeTasks::TASK_STATE_COMPLETE) {
            $data['status'] = 2;//充电完成
        } elseif ($model->task_state == ChargeTasks::TASK_STATE_INIT) {
            $data['status'] = 3;//初始化，还没通电
        } else {
            $begin = strtotime($model->begin_at);
            $time = time() - $begin;
            $mins = floor($time / 60);
            $data['mins'] = sprintf('%02s', $mins);
        }

        return $data;
    }

    /**
     * 开始充电
     * @param $taskId
     * @return bool
     */
    public static function powerOn($taskId)
    {
        if (!$model = ChargeTasks::find($taskId)) {
            return false;
        }
        Log::debug('task info ' . $model->toJson() . ' is charging now...');
        //开始充电
        $duration = $model->duration;
        $model->task_state = ChargeTasks::TASK_STATE_CHARGING;
        $model->begin_at = date('Y-m-d H:i:s');
        $model->expect_end_at = date('Y-m-d H:i:s', strtotime("+$duration seconds"));
        return $model->save();
    }

    /**
     * @param $userId
     * @return bool|int|mixed
     */
    public static function getUnfinishTaskIdByUserId($userId)
    {
        $model = ChargeTasks::whereUserId($userId)->orderByDesc('id')->first();
        if(!$model || $model->task_state != ChargeTasks::TASK_STATE_CHARGING){
            return false;
        }
        return $model->id;
    }

    const TEMPLATE_ID_AB = '8ySltzpdZn80ymIGD6-2N6vhQ1YFGbjRMZ0v8js22YA';

    public static function sendEndAbMessage($taskId)
    {
        $data = [
            'template_id'=>self::TEMPLATE_ID_AB,
            'data'=>[
                'keyword1'=>['value'=>'充电异常中断','color'=>'#173177'],
                'keyword2'=>['value'=>'请到车棚查看原因','color'=>'#173177'],
            ],
        ];
        return self::sendMessageToUser($taskId, $data);
    }

    const TEMPLATE_ID_END = 'kJuQgZvKVkuGr_uK16rrXg0NKYeLaoHWiEq9uvE7x14';

    public static function sendEndMessage($taskId)
    {
        $data = [
            'template_id'=>self::TEMPLATE_ID_END,
            'data'=>[
                'keyword1'=>['value'=>'Y1.00','color'=>'#173177'],
                'keyword2'=>['value'=>'24分钟','color'=>'#173177'],
                //'keyword3'=>['value'=>'请到车棚查看原因','color'=>'#173177'],
            ],
        ];
        return self::sendMessageToUser($taskId, $data);
    }

    public static function sendMessageToUser($taskId,array $data)
    {
        $model = ChargeTasks::find($taskId);
        if(!$model){
            return false;
        }
        $formId = $model->form_id;
        $openId = User::getOpenIdById($model->user_id);
        $default = [
            'touser'=>$openId,
            'form_id'=>$formId,
        ];
        $data = array_merge($data, $default);
        $wxapi = new WxApi();
        return $wxapi->sendMessage($data);
    }

}