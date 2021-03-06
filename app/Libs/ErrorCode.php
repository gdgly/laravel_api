<?php

namespace App\Libs;


class ErrorCode
{

    //接入逻辑层
    public static $errParams = 1001;//参数错误

    public static $errSign = 1002;//签名错误


    //业务层
    public static $tokenExpire = 2001;//token过期

    public static $codeInvalid = 2002;//微信code失效

    public static $sessionKeyExpire = 2003;//微信sessionkey过期

    //public static $invalidDeviceId = 2004;//设备id不存在

    public static $chargeTaskNotFind = 2005;//充电任务不存在

    public static $qrCodeNotFind = 2006;//找不到充电二维码

    public static $deviceNotOnline = 2007;//设备离线

    public static $deviceNotUseful = 2008;//设备不可用

    public static $balanceNotEnough = 2009;//用户余额不足

    public static $chargeNotFinishYet = 2010;//充电还未结束

    public static $refundFail = 2011;//提交退款失败，请确认还有余额

    public static $isChargingNow = 2012;//正在充电中

    public static $phoneInvalid = 2013;//手机格式不正确

    public static $phoneVerifyCodeSendFailed = 2014;//手机验证码发送失败

    public static $isChargingAndNeedWait = 2015;//充电口被占用，需等待

    public static $cardInvalid = 2016;//非法卡片

    public static $notInWhiteList = 2017;//非福利卡白名单用户

    public static $hasGotCard = 2018;//已经领取过福利卡，无需再次领取

    public static $openBoxTimeout = 2019;//箱子状态超时

    public static $cabinetUnuseful = 2020;//柜字不可用

    public static $batteryNotEnough = 2021;//无可用电池

    public static $batteryNotRegister = 2022;//电池未注册

    public static $appointmentExists = 2023;//已预约

    public static $notFindReplaceTask = 2024;//未发现换电任务

    public static $notBindBattery = 2025;//未绑定电池

    public static $notFindTask = 2026;//未找到task

    public static $notOpsUser = 2027;//未运维人员

    public static $operationFail = 2028;//操作失败

    public static $isReplacing = 2029;//正在换电

    public static $isOpsNow = 2030;//正在运维

    public static $needWait = 2031;//请等待

    public static $replaceFail = 2032;//换电失败

    public static $batteryBindRepeat= 2033;//电池绑定重复

    public static $hasNoBatteryForAppointment = 2034;//可用电池已被预约

    public static function getErrMsg()
    {
        return [
            self::$qrCodeNotFind => '二维码有误',
            self::$deviceNotOnline => '设备离线,{device_no}-{port_no}',
            self::$deviceNotUseful => '充电口暂不可用，请稍后再试，{device_no}-{port_no}',
            self::$balanceNotEnough => '余额不足，请充值',
            self::$isChargingNow => '充电口被占用，请换一个充电口',
            self::$isChargingAndNeedWait => '充电口被占用，请换一个充电口或等待{mins}分钟',
            self::$hasGotCard => '您已经领取过福利卡，无需再次领取',
            self::$cabinetUnuseful => '充电柜离线不可用',
            self::$batteryNotEnough => '无可用电池',
            self::$batteryNotRegister => '电池未注册',
            self::$appointmentExists => '已预约',
            self::$notFindReplaceTask => '未发现换电任务',
            self::$notBindBattery => '请先扫描电池上的二维码',
            self::$notFindTask => 'not find task',
            self::$notOpsUser => '非运维人员',
            self::$operationFail => '操作失败',
            self::$isReplacing => '正在换电中',
            self::$isOpsNow => '正在运维中',
            self::$needWait => '请等待，换电柜正在使用中...',
            self::$replaceFail => '换电失败,请联系客服',
            self::$batteryBindRepeat => '电池已被绑定',
            self::$hasNoBatteryForAppointment => '可用电池已被预约',
        ];
    }


}