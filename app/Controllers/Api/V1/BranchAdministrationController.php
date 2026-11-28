<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiInput;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use App\Modules\Administration\BranchDirectoryManager;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class BranchAdministrationController extends BaseController
{
    private function actor(): ?int
    {
        $user=auth('session')->user();
        if (!$user||!$user->active||$user->isBanned()) return null;
        $id=(int)$user->id;$db=db_connect();
        if (!(new SessionVersion($db))->isCurrent($id)
            ||!(new VerifiedIdentifierResolver($db))->hasVerifiedIdentifier($id)
            ||(int)session()->get('iedpu_mfa_verified_user')!==$id
            ||!(new ScopeAuthorizer($db))->requiresPendingMfa($id)) return null;
        return $id;
    }
    private function denied(int $status,string $code): ResponseInterface
    {return ApiResponder::error($this->request,$this->response,$status,$code,'Branch administration request cannot be completed.');}
    private function body(): ?array
    {$p=ApiInput::jsonObject($this->request,8192);return $p['ok']?$p['data']:null;}

    public function index(): ResponseInterface
    {
        $id=$this->actor();if(!$id)return $this->denied(401,'unauthenticated');
        try {
            $manager=new BranchDirectoryManager(db_connect());
            return ApiResponder::success($this->request,$this->response,[
                'items'=>$manager->list($id),'references'=>$manager->references($id)
            ]);
        } catch(Throwable){return $this->denied(403,'branch_management_unavailable');}
    }
    public function create(): ResponseInterface
    {
        return $this->save(null);
    }
    public function update(int $id): ResponseInterface
    {
        return $this->save($id);
    }
    private function save(?int $id): ResponseInterface
    {
        $actor=$this->actor();if(!$actor)return $this->denied(401,'unauthenticated');
        $data=$this->body();if(!$data)return $this->denied(422,'invalid_request');
        try {
            $branchId=(new BranchDirectoryManager(db_connect()))->save($actor,$data,$id);
            return ApiResponder::success($this->request,$this->response,['branch_id'=>$branchId],$id===null?201:200);
        }catch(Throwable){return $this->denied(403,'branch_change_denied');}
    }
    public function activate(int $id): ResponseInterface
    {
        return $this->changeStatus($id,true);
    }
    public function deactivate(int $id): ResponseInterface
    {
        return $this->changeStatus($id,false);
    }
    private function changeStatus(int $id,bool $active): ResponseInterface
    {
        $actor=$this->actor();if(!$actor)return $this->denied(401,'unauthenticated');
        $data=$this->body();if(!$data||array_diff(array_keys($data),['reason','reference'])!==[]
            ||!is_string($data['reason']??null)||!is_string($data['reference']??''))return $this->denied(422,'invalid_request');
        try {
            (new BranchDirectoryManager(db_connect()))->setStatus($actor,$id,$active,$data['reason'],$data['reference']??'');
            return ApiResponder::success($this->request,$this->response,['updated'=>true]);
        }catch(Throwable){return $this->denied(403,'branch_status_change_denied');}
    }
    public function history(int $id): ResponseInterface
    {
        $actor=$this->actor();if(!$actor)return $this->denied(401,'unauthenticated');
        try{return ApiResponder::success($this->request,$this->response,['items'=>(new BranchDirectoryManager(db_connect()))->history($actor,$id)]);}
        catch(Throwable){return $this->denied(403,'branch_history_unavailable');}
    }
}
