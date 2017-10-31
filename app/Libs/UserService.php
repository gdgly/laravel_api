<?php
namespace App\Libs;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

class UserService
{

    const LOGIN_TYPE_WEIXIN = 0;
    const LOGIN_TYPE_PHONE = 1;

    const WX_APPID = 'wxa18b666bc3bec5d9';
    const WX_SECRET = 'f75ba339ea5013cdf82c018f8ff9a75d';

    public static function getOpenid($code)
    {
        $appid = self::WX_APPID;
        $secert = self::WX_SECRET;
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secert&js_code=$code&grant_type=authorization_code";
        $json = file_get_contents($url);

        $arr = json_decode($json, true);
        if(isset($arr['openid'])){
            return $arr;
        }else{
            return false;
        }
    }

    public static function loginByCode($code)
    {
        if(!$data = self::getOpenid($code)){
            return false;
        }
//        "session_key":"siDizJgW82HgNEvnPMZCKg==",
//                    "expires_in":7200,
//                    "openid":"oovMR0WZOnvIRn1xKEGPkyFhjkPM"

        $uuid = Uuid::uuid1();
        $token = $uuid->getHex();    //32位字符串方法

        $openid = $data['openid'];

        $user = User::firstOrCreate(['openid'=>$openid])->toArray();

        //token缓存
        Cache::put($token, json_encode([
            'type'=>self::LOGIN_TYPE_WEIXIN,//0微信登录,1手机登录
            'openid'=>$openid,
            'session_key'=>$data['session_key'],
            'uid'=>$user['id'],
            'phone'=>$user['phone'],
        ]),$data['expires_in']/60);

        return $token;
    }

    public static function bindPhone($token, $phone)
    {
        $data = Cache::get($token);
        $data = json_decode($data, true);
        $data['phone'] = $phone;

        $uid = $data['uid'];
        User::where(['id'=>$uid])->update(['phone'=>$phone]);

        Cache::put($token, json_encode($data),120);
    }

}