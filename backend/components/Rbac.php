<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-03-15 21:16
 */

namespace backend\components;

use yii;
use backend\models\AdminRoles;
use yii\base\Component;
use backend\models\AdminRoleUser;
use backend\models\AdminRolePermission;
use common\libs\Constants;
use yii\web\ForbiddenHttpException;

class Rbac extends Component
{

    /**
     * 超级管理员权用户，不受权限控制
     * @var array
     */
    private $_superAdministrators = ['admin'];

    /**
     * 无需权限控制的控制器
     * @var array
     */
    private $_noNeedAuthentication = ['site/login', 'site/main'];

    private $_role_id;

    private $_roleName;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        foreach ($this->_noNeedAuthentication as &$v) {
            if (substr_count($v, '/') < 2) {
                $v = yii::$app->id . '/' . $v;
            }
        }
    }

    /**
     * 检查当前管理是否有权限执行此操作
     *
     * @throws \yii\web\ForbiddenHttpException
     */
    public static function checkPermission()
    {
        if (! yii::$app->user->isGuest) {
            if (yii::$app->rbac->_checkPermission() === false) {
                if (yii::$app->getRequest()->getIsAjax()) {
                    yii::$app->getResponse()->content = json_encode(['code' => 1001, 'message' => yii::t("app", "Permission denied")]);
                    yii::$app->getResponse()->send();
                } else {
                    throw new ForbiddenHttpException(yii::t("app", "Permission denied"));
                }
                exit();
            }
        }
        if (yii::$app->user->isGuest && ! in_array(Yii::$app->controller->id . '/' . Yii::$app->controller->action->id, [
                'site/login',
                'user/request-password-reset',
                'user/reset-password',
                'site/captcha',
                'site/language'
            ]) && ! in_array(Yii::$app->controller->module->id, ['debug'])
        ) {
            yii::$app->controller->redirect(['site/login'])->send();
            exit;
        }
    }

    public function _checkPermission($uid = '')
    {
        if ($uid == 1) {
            return true;
        }
        if (in_array(yii::$app->user->identity->username, $this->_superAdministrators)) {
            return true;
        }
        $route = strtolower(Yii::$app->controller->module->id . '/' . Yii::$app->controller->id . '/' . Yii::$app->controller->action->id);
        if (in_array($route, $this->_noNeedAuthentication)) {
            return true;
        }
        $this->getRoleId($uid);
        if ($this->_role_id == 1) {
            return true;
        }
        $permissions = AdminRolePermission::getPermissionsByRoleId($this->_role_id);
        $method = strtolower(yii::$app->request->getMethod());
        foreach ($permissions as $permission) {
            //后台添加路由时是没有添加$app->id ,所以无论在何种情况下都需要加上此ID,否则通不过
            //if (substr_count($permission['url'], '/') < 2) {
                $permission['url'] = yii::$app->id . $permission['url'];
            //}
            if (strtolower($permission['url']) == $route) {
                if ($permission['method'] == Constants::HTTP_METHOD_ALL || Constants::getHttpMethodItems($permission['method']) == $method) {
                    return true;
                }
            }
        }
        return false;
    }

    public function setSuperAdministrators(array $params)
    {
        $this->_superAdministrators = $params;
    }

    public function getSuperAdministrators()
    {
        return $this->_superAdministrators;
    }

    public function setNoNeedAuthentication(array $params)
    {
        $this->_noNeedAuthentication = $params;
    }

    public function getNoNeedAuthentication()
    {
        return $this->_noNeedAuthentication;
    }

    public function getRoleId($uid = '')
    {
        if ($uid == '') {
            yii::$app->user->identity->id;
        }
        $this->_role_id = AdminRoleUser::getRoleIdByUid($uid);
        return $this->_role_id;
    }

    public function getRoleName()
    {
        $this->_roleName = AdminRoles::getRoleNameByUid();
        return $this->_roleName;
    }

}