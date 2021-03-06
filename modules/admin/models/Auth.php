<?php

namespace app\modules\admin\models;

use Yii;
use app\common\behaviors\TimestampBehavior;

/**
 * This is the model class for table "auth_item".
 *
 * @property string $name
 * @property integer $type
 * @property string $description
 * @property string $rule_name
 * @property string $data
 * @property integer $created_at
 * @property integer $updated_at
 * @property string  $menus
 *
 * @property AuthAssignment[] $authAssignments
 * @property AuthRule $ruleName
 * @property AuthItemChild[] $authItemChildren
 */
class Auth extends \app\common\models\Model
{
    /**
     * Auth type
     */
    const TYPE_ROLE = 1;
    const TYPE_PERMISSION = 2;

    public $_permissions = [];

    /**
     * behaviors() 定义行为
     * @return array
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%auth_item}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'required'],
            ['name', 'match', 'pattern' => '/^([a-zA-Z0-9_-]|([a-zA-z0-9_-]\\/[0-9_-a-zA-z]))+$/'],
            ['name', 'string', 'min' => 3, 'max' => 64],
            ['name', 'validatePermission'],
            ['description', 'string', 'min' => 1, 'max' => 400],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name'        => '名称',
            'description' => '说明',
            'created_at'  => '创建时间',
            'updated_at'  => '修改时间',
        ];
    }

    public function validatePermission()
    {
        if (!$this->hasErrors()) {
            $auth = Yii::$app->getAuthManager();
            if ($this->isNewRecord && $auth->getPermission($this->name)) {
                $this->addError('name', Yii::t('auth', 'This name already exists.'));
            }
            if ($this->isNewRecord && $auth->getRole($this->name)) {
                $this->addError('name', Yii::t('auth', 'This name already exists.'));
            }
        }
    }

    public function createPermission()
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $permission = $auth->createPermission($this->name);
            $permission->description = $this->description;
            $auth->add($permission);
            $admin = $auth->getRole(Yii::$app->params['adminRoleName']);
            return $auth->addChild($admin, $permission);
        }
        return false;
    }

    public function updatePermission($name)
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $permission = $auth->getPermission($name);
            $permission->description = $this->description;
            return $auth->update($name, $permission);
        }
        return false;
    }

    // 添加角色信息
    public function createRole($permissions)
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $role = $auth->createRole($this->name);
            $role->description = $this->description;;
            if ($auth->add($role)) {
                foreach ($permissions as $permission) {
                    $obj = $auth->getPermission($permission);
                    $auth->addChild($role, $obj);
                }

                // 将角色添加给用户
                $uid = Yii::$app->admin->id;
                if ($uid != 1) $auth->assign($role, $uid);
                return true;
            }
        }
        return false;
    }

    // 修改权限信息
    public function updateRole($name, $permissions)
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $role = $auth->getRole($name);
            $role->description = $this->description;
            // save role
            if ($auth->update($name, $role)) {
                // remove old permissions
                $oldPermissions = $auth->getPermissionsByRole($name);
                foreach($oldPermissions as $permission) {
                    $auth->removeChild($role, $permission);
                }

                // add new permissions
                foreach ($permissions as $permission) {
                    $obj = $auth->getPermission($permission);
                    $auth->addChild($role, $obj);
                }
                return true;
            }
        }
        return false;
    }

    public function loadRolePermissions($name) {
        $models = Yii::$app->authManager->getPermissionsByRole($name);
        foreach($models as $model) {
            $this->_permissions[] = $model->name;
        }
    }

    public static function hasUsersByRole($name) {
        $tablePrefix = Yii::$app->getDb()->tablePrefix;
        return Auth::find()
                ->where(['name' => $name])
                ->InnerJoin("{$tablePrefix}auth_assignment", ['item_name' => $name])
                ->count();
    }

    public static function hasRolesByPermission($name) {
        $tablePrefix = Yii::$app->getDb()->tablePrefix;
        return Auth::find()
                ->where(['name' => $name])
                ->InnerJoin("{$tablePrefix}auth_item_child", ['child' => $name])
                ->count();
    }
}
