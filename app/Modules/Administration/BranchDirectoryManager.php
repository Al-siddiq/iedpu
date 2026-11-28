<?php
declare(strict_types=1);

namespace App\Modules\Administration;

use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** P3-WP03-A branch directory management. Every write is globally permission-gated and audited. */
final class BranchDirectoryManager
{
    public function __construct(private readonly BaseConnection $db) {}

    public function list(int $actor): array
    {
        $this->authorise($actor);
        return $this->db->query(
            "SELECT b.id,b.public_id,b.zone_id,z.zone_name,b.state_id,s.state_name,
              b.branch_code,b.branch_name,b.source_name,b.locality,b.lga_or_district,
              b.office_address,b.contact_phone,b.contact_email,b.recognition_status,
              b.operational_status,b.recognition_reference,b.recognition_date,b.notes
             FROM branches b JOIN org_zones z ON z.id=b.zone_id
             LEFT JOIN geo_states s ON s.id=b.state_id
             WHERE z.is_project_scope=1 ORDER BY z.zone_name,b.branch_name"
        )->getResultArray();
    }

    public function references(int $actor): array
    {
        $this->authorise($actor);
        return [
            'zones'=>$this->db->table('org_zones')->select('id,zone_name')
                ->where('is_project_scope',1)->where('status','active')->orderBy('zone_name')->get()->getResultArray(),
            'states'=>$this->db->table('geo_states')->select('id,state_name,geo_type')
                ->where('is_active',1)->orderBy('state_name')->get()->getResultArray(),
        ];
    }

    public function save(int $actor, array $data, ?int $branchId=null): int
    {
        $this->authorise($actor);
        $allowed=['zone_id','state_id','branch_code','branch_name','source_name','locality','lga_or_district',
            'office_address','contact_phone','contact_email','recognition_status','recognition_reference',
            'recognition_date','notes','reason'];
        if (array_diff(array_keys($data),$allowed)!==[]) throw new RuntimeException('Unexpected branch fields.');
        $reason=trim((string)($data['reason']??''));
        $name=trim((string)($data['branch_name']??''));
        $zoneId=filter_var($data['zone_id']??null,FILTER_VALIDATE_INT);
        if ($reason===''||strlen($reason)>500||$name===''||strlen($name)>160||$zoneId===false||$zoneId<1) {
            throw new RuntimeException('Branch name, active project zone and reason are required.');
        }
        $zone=$this->db->table('org_zones')->where('id',$zoneId)->where('is_project_scope',1)->where('status','active')->get()->getRowArray();
        if (!$zone) throw new RuntimeException('Select an active project zone.');
        $recognition=(string)($data['recognition_status']??'pending_confirmation');
        if (!in_array($recognition,['approved','provisional','pending_confirmation','not_recognised'],true)) throw new RuntimeException('Invalid recognition status.');
        $stateId=$data['state_id']??null;
        if ($stateId!==null && $stateId!=='') {
            $state=$this->db->table('geo_states')->where('id',(int)$stateId)->where('is_active',1)->get()->getRowArray();
            if (!$state) throw new RuntimeException('Select an active state or FCT.');
            $stateId=(int)$stateId;
        } else $stateId=null;
        $fields=[
            'zone_id'=>(int)$zoneId,'state_id'=>$stateId,
            'branch_code'=>$this->nullable($data['branch_code']??null,40),
            'branch_name'=>$name,'source_name'=>$this->nullable($data['source_name']??null,160),
            'locality'=>$this->nullable($data['locality']??null,160),
            'lga_or_district'=>$this->nullable($data['lga_or_district']??null,160),
            'office_address'=>$this->nullable($data['office_address']??null,500),
            'contact_phone'=>$this->nullable($data['contact_phone']??null,40),
            'contact_email'=>$this->nullable($data['contact_email']??null,190),
            'recognition_status'=>$recognition,
            'recognition_reference'=>$this->nullable($data['recognition_reference']??null,150),
            'recognition_date'=>$this->date($data['recognition_date']??null),
            'notes'=>$this->nullable($data['notes']??null,5000),
            'updated_at'=>gmdate('Y-m-d H:i:s.u'),'updated_by_user_id'=>$actor,
        ];
        $this->db->transBegin();
        try {
            $before=null;
            if ($branchId!==null) {
                $before=$this->db->table('branches')->where('id',$branchId)->get()->getRowArray();
                if (!$before) throw new RuntimeException('Branch not found.');
                $this->db->table('branches')->where('id',$branchId)->update($fields);
                $action='updated';
            } else {
                $fields['public_id']=$this->uuid();
                $fields['operational_status']='inactive';
                $fields['created_at']=gmdate('Y-m-d H:i:s.u');
                $fields['created_by_user_id']=$actor;
                $this->db->table('branches')->insert($fields);
                $branchId=(int)$this->db->insertID();
                $action='created';
            }
            $after=$this->db->table('branches')->where('id',$branchId)->get()->getRowArray();
            $this->db->table('branch_change_history')->insert([
                'branch_id'=>$branchId,'actor_user_id'=>$actor,'action_code'=>$action,'reason'=>$reason,
                'before_json'=>$before?json_encode($before,JSON_THROW_ON_ERROR):null,
                'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s.u')
            ]);
            if (!$this->db->transStatus()) throw new RuntimeException('Branch change could not be saved.');
            (new AuditTrail($this->db))->append([
                'action_key'=>'organisation.branch_'.$action,'module_code'=>'administration',
                'event_category'=>'system','outcome'=>'success','actor_user_id'=>$actor,
                'subject_type'=>'branch','subject_id'=>$branchId,'zone_id'=>(int)$zoneId,
                'branch_id'=>$branchId,'reason'=>$reason,'request_id'=>RequestId::current()
            ]);
            $this->db->transCommit();
            return $branchId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function setStatus(int $actor,int $branchId,bool $activate,string $reason,string $reference=''): void
    {
        $permission=$activate?'branches.activate':'branches.manage';
        if (!(new ScopeAuthorizer($this->db))->allows($actor,$permission,'global')) throw new RuntimeException('Branch status change is not authorised.');
        $reason=trim($reason);
        if ($branchId<1||$reason===''||strlen($reason)>500) throw new RuntimeException('A branch and reason are required.');
        $this->db->transBegin();
        try {
            $branch=$this->db->query('SELECT b.*,z.status AS zone_status,z.is_project_scope FROM branches b JOIN org_zones z ON z.id=b.zone_id WHERE b.id=? FOR UPDATE',[$branchId])->getRowArray();
            if (!$branch||(int)$branch['is_project_scope']!==1) throw new RuntimeException('Branch not found.');
            if ($activate && ($branch['recognition_status']!=='approved'||$branch['zone_status']!=='active')) {
                throw new RuntimeException('Only an approved, recognised branch in an active project zone can be activated.');
            }
            $status=$activate?'active':'inactive';
            $this->db->table('branches')->where('id',$branchId)->update([
                'operational_status'=>$status,
                'recognition_reference'=>$reference!==''?$reference:$branch['recognition_reference'],
                'updated_at'=>gmdate('Y-m-d H:i:s.u'),'updated_by_user_id'=>$actor
            ]);
            $after=$this->db->table('branches')->where('id',$branchId)->get()->getRowArray();
            $action=$activate?'activated':'deactivated';
            $this->db->table('branch_change_history')->insert([
                'branch_id'=>$branchId,'actor_user_id'=>$actor,'action_code'=>$action,'reason'=>$reason,
                'before_json'=>json_encode($branch,JSON_THROW_ON_ERROR),
                'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s.u')
            ]);
            (new AuditTrail($this->db))->append([
                'action_key'=>'organisation.branch_'.$action,'module_code'=>'administration',
                'event_category'=>'system','outcome'=>'success','actor_user_id'=>$actor,
                'subject_type'=>'branch','subject_id'=>$branchId,'zone_id'=>(int)$branch['zone_id'],
                'branch_id'=>$branchId,'reason'=>$reason,'request_id'=>RequestId::current()
            ]);
            if (!$this->db->transStatus()) throw new RuntimeException('Branch status change failed.');
            $this->db->transCommit();
        } catch(Throwable $e) {$this->db->transRollback();throw $e;}
    }

    public function history(int $actor,int $branchId): array
    {
        $this->authorise($actor);
        return $this->db->table('branch_change_history h')
            ->select('h.action_code,h.reason,h.before_json,h.after_json,h.created_at,u.id AS actor_user_id')
            ->join('users u','u.id=h.actor_user_id')->where('h.branch_id',$branchId)
            ->orderBy('h.id','DESC')->limit(100)->get()->getResultArray();
    }

    private function authorise(int $actor): void
    {
        if (!(new ScopeAuthorizer($this->db))->allows($actor,'branches.manage','global')) {
            throw new RuntimeException('Branch administration is not authorised.');
        }
    }
    private function nullable(mixed $v,int $max): ?string {$v=trim((string)($v??''));if($v==='' )return null;if(strlen($v)>$max)throw new RuntimeException('A branch field is too long.');return $v;}
    private function date(mixed $v): ?string {$v=trim((string)($v??''));if($v==='')return null;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new RuntimeException('Recognition date must be YYYY-MM-DD.');return $v;}
    private function uuid(): string {$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
}
