<?php
declare(strict_types=1);
namespace App\Controllers\Api\V1;
use App\Controllers\BaseController;
use App\Libraries\Api\ApiInput;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\ApprovedInvitationRelease;
use App\Libraries\Auth\InvitationActivation;
use App\Libraries\Auth\InvitationIssuerSessionGate;
use App\Libraries\Auth\SessionVersion;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
/** CSRF-protected explicit routes; all privileged calls require Shield+MFA. */
final class InvitationController extends BaseController {
 private function reject(int $status,string $code): ResponseInterface {
  return ApiResponder::error($this->request,$this->response,$status,$code,'Invitation request cannot be completed.');
 }
 private function actor(): ?int {
  $user=auth('session')->user();
  if ($user===null||!$user->active||$user->isBanned()) return null;
  $id=(int)$user->id;
  if (!(new SessionVersion(db_connect()))->isCurrent($id)
   ||!InvitationIssuerSessionGate::allows($id,$id,true,session()->get('iedpu_mfa_verified_user'),true)
   ||db_connect()->table('portal_totp_factors')->where('user_id',$id)->countAllResults()===0) return null;
  return $id;
 }
 private function json(array $keys): ?array {
  $parsed=ApiInput::jsonObject($this->request,4096);
  if (!$parsed['ok']) return null;
  $actual=array_keys($parsed['data']);sort($actual);sort($keys);
  if ($actual!==$keys) return null;
  return $parsed['data'];
 }
 private function rate(int $id,string $action): bool {
  return service('throttler')->check('iedpu-invite-'.$action.'-'.hash('sha256',(string)$id),10,3600)
   && service('throttler')->check('iedpu-invite-net-'.hash('sha256',$this->request->getIPAddress()),30,3600);
 }
 public function requestApproval(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'request')) return $this->reject(429,'rate_limited');
   $d=$this->json(['target_user_id','identifier_id','scope','zone_id','branch_id','reason']);
   if ($d===null||!is_int($d['target_user_id'])||!is_int($d['identifier_id'])||!is_string($d['scope'])
    ||!is_string($d['reason'])||!in_array($d['scope'],['global','zone','branch'],true)
    ||($d['zone_id']!==null&&!is_int($d['zone_id']))||($d['branch_id']!==null&&!is_int($d['branch_id']))) return $this->reject(422,'invalid_request');
   $requestId=(new ApprovedInvitationRelease(db_connect()))->request($id,$d['target_user_id'],$d['identifier_id'],$d['scope'],$d['zone_id'],$d['branch_id'],$d['reason']);
   return ApiResponder::success($this->request,$this->response,['approval_request_id'=>$requestId],201);
  }catch(Throwable){return $this->reject(403,'invitation_denied');}
 }
 public function decide(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'decide')) return $this->reject(429,'rate_limited');
   $d=$this->json(['approval_request_id','approve','reason']);
   if ($d===null||!is_int($d['approval_request_id'])||!is_bool($d['approve'])||!is_string($d['reason'])) return $this->reject(422,'invalid_request');
   (new ApprovedInvitationRelease(db_connect()))->decide($id,$d['approval_request_id'],$d['approve'],$d['reason']);
   return ApiResponder::success($this->request,$this->response,['recorded'=>true]);
  }catch(Throwable){return $this->reject(403,'approval_denied');}
 }
 public function issue(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'issue')) return $this->reject(429,'rate_limited');
   $d=$this->json(['approval_request_id']);
   if ($d===null||!is_int($d['approval_request_id'])) return $this->reject(422,'invalid_request');
   (new ApprovedInvitationRelease(db_connect()))->issue($id,$d['approval_request_id']);
   return ApiResponder::success($this->request,$this->response,['queued'=>true],202);
  }catch(Throwable){return $this->reject(403,'invitation_denied');}
 }
 public function activate(): ResponseInterface {
  try {
   $parsed=ApiInput::jsonObject($this->request,2048);
   if (!$parsed['ok']) return $this->reject(422,'invalid_request');
   $d=$parsed['data'];
   if (count($d)!==3||!array_key_exists('user_id',$d)||!array_key_exists('token',$d)||!array_key_exists('password',$d)||!is_int($d['user_id'])
    ||!is_string($d['token'])||!is_string($d['password'])) return $this->reject(422,'invalid_request');
   if (!service('throttler')->check('iedpu-invite-activation-'.hash('sha256',$this->request->getIPAddress()),8,3600)
    ||!service('throttler')->check('iedpu-invite-activation-user-'.hash('sha256',(string)$d['user_id']),8,3600)) return $this->reject(429,'rate_limited');
   $result=(new InvitationActivation(db_connect()))->complete($d['user_id'],$d['token'],$d['password']);
   if ($result==='weak') return $this->reject(422,'weak_password');
   if ($result!=='activated') return $this->reject(400,'invalid_invitation');
   return ApiResponder::success($this->request,$this->response,['activated'=>true]);
  }catch(Throwable){return $this->reject(400,'invalid_invitation');}
 }
}
