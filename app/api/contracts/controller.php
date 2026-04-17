<?php
use App\Model\BaseModel;
use App\Model\Crud;

class contractsComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'c.id','readonly' => true, 'saved' => false],
		'procedure_id' => ['field' => 'c.procedure_id'],
		'subject_id' => ['field' => 'c.subject_id'],
		'admin_period_start' => ['field' => 'c.admin_period_start'],
		'admin_period_end' => ['field' => 'c.admin_period_end'],
		'fiscal_year' => ['field' => 'c.fiscal_year'],
		'period_id' => ['field' => 'c.period_id'],
		'contract_id' => ['field' => 'c.contract_id'],
		'partida_type' => ['field' => 'c.partida_type'],
		'partida_id' => ['field' => 'c.partida_id'],
		'status_id' => ['field' => 'c.status_id'],
		'call_link' => ['field' => 'c.call_link'],
		'call_date' => ['field' => 'c.call_date'],
		'work_description' => ['field' => 'c.work_description'],
		'clarification_meeting_date' => ['field' => 'c.clarification_meeting_date'],
		'proposals_url' => ['field' => 'c.proposals_url'],
		'proposal_url' => ['field' => 'c.proposal_url'],
		'provider_id' => ['field' => 'c.provider_id'],
		'organizer_admin_unit_id' => ['field' => 'c.organizer_admin_unit_id'],
		'applicant_admin_unit_id' => ['field' => 'c.applicant_admin_unit_id'],
		'admin_unit_type_id' => ['field' => 'c.admin_unit_type_id'],
		'contract_number' => ['field' => 'c.contract_number'],
		'contract_date' => ['field' => 'c.contract_date'],
		'contract_type_id' => ['field' => 'c.contract_type_id'],
		'total_amount' => ['field' => 'c.total_amount', 'default' => 0],
		'min_amount' => ['field' => 'c.min_amount', 'default' => 0],
		'max_amount' => ['field' => 'c.max_amount', 'default' => 0],
		'subtotal' => ['field' => 'c.subtotal', 'default' => 0],
		'contract_link' => ['field' => 'c.contract_link'],
		'area_in_charge' => ['field' => 'c.area_in_charge'],
		'contract_updated_at' => ['field' => 'c.contract_updated_at'],
		'notes' => ['field' => 'c.notes'],
		'organization_notes' => ['field' => 'c.organization_notes'],
		'information_date' => ['field' => 'c.information_date'],
		'amount_was_exceeded' => ['field' => 'c.amount_was_exceeded', 'default' => 0],
		'exceeded_amount' => ['field' => 'c.exceeded_amount', 'default' => 0],
		'contract_backup' => ['field' => 'c.contract_backup'],
		'announcement_backup' => ['field' => 'c.announcement_backup'],
		'created_at' => ['field' => 'c.created_at', 'readonly' => true, 'saved' => false],
		'updated_at' => ['field' => 'c.updated_at', 'readonly' => true, 'saved' => false],
		'provider_name' => ['field' => 'pr.name', 'readonly' => true, 'saved' => false],
		'organizer_admin_unit_name' => ['field' => 'au1.name', 'readonly' => true, 'saved' => false],
		'applicant_admin_unit_name' => ['field' => 'au2.name', 'readonly' => true, 'saved' => false],
		'admin_unit_type_name' => ['field' => 'ut.name', 'readonly' => true, 'saved' => false],
		'contract_type_name' => ['field' => 'ct.name', 'readonly' => true, 'saved' => false],
		'period_name' => ['field' => 'per.name', 'readonly' => true, 'saved' => false],
		'procedure_name' => ['field' => 'p.name', 'readonly' => true, 'saved' => false],
		'subject_name' => ['field' => 'm.name', 'readonly' => true, 'saved' => false],
		'partida_type_name' => ['field' => 'pt.name', 'readonly' => true, 'saved' => false],
		'status_name' => ['field' => 's.name', 'readonly' => true, 'saved' => false]
	];

	protected $get_params = [
		'table' => 'contratos c',
		'filters' => [],
		'joins' => [
			[
				'table' => 'c_procedures p',
				'match' => ['c.procedure_id', 'p.id'],
			]
			,[
				'table' => 'c_materia m',
				'match' => ['c.subject_id', 'm.id'],
			],
			[
				'table' => 'c_partidas pt',
				'match' => ['c.partida_type', 'pt.id'],
			],
			[
				'table' => 'c_estatus s',
				'match' => ['c.status_id', 's.id'],
			],
			[
				'table' => 'proveedores pr',
				'match' => ['c.provider_id', 'pr.id'],
			],
			[
				'table' => 'admin_units au1',
				'match' => ['c.organizer_admin_unit_id', 'au1.id'],
			],
			[
				'table' => 'admin_units au2',
				'match' => ['c.applicant_admin_unit_id', 'au2.id'],
			],
			[
				'table' => 'unit_types ut',
				'match' => ['c.admin_unit_type_id', 'ut.id'],
			],
			[
				'table' => 'c_tipo ct',
				'match' => ['c.contract_type_id', 'ct.id'],
			],[
				'table' => 'c_periods per',
				'match' => ['c.period_id', 'per.id'],
			]
		],
		'search' => []
	];

	protected $rules = [
		'id' => 'required|numeric|unique:contratos:id',
		'procedure_id' => 'required|max:11:unique:c_procedures:id',
		'subject_id' => 'required|max:100|unique:c_materia:id',
		'admin_period_start' => 'required|numeric|min:4|max:4',
		'admin_period_end' => 'required|numeric|min:4|max:4',
		'fiscal_year' => 'required|numeric|min:4|max:4',
		'period_id' => 'required|max:11|numeric|unique:c_periods:id',
		'contract_id' => 'required|max:50',
		'partida_type' => 'required|numeric|max:11|unique:c_partidas:id',
		'partida_id' => 'max:20',
		'status_id' => 'required|max:11|numeric|unique:c_estatus:id',
		'call_link' => 'url',
		'call_date' => 'date_format:Y-m-d',
		'clarification_meeting_date' => 'date_format:Y-m-d',
		'porposals_url' => 'url',
		'proposal_url' => 'url',
		'provider_id' => 'required|max:11|numeric|unique:proveedores:id',
		'organizer_admin_unit_id' => 'required|max:11|numeric|unique:admin_units:id',
		'applicant_admin_unit_id' => 'required|max:11|numeric|unique:admin_units:id',
		'admin_unit_type_id' => 'required|max:11|numeric|unique:unit_types:id',
		'contract_number' => 'required|max:100',
		'contract_date' => 'date_format:Y-m-d',
		'contract_type_id' => 'required|max:11|numeric|unique:c_tipo:id',
		'total_amount' => 'decimal',
		'min_amount' => 'decimal',
		'max_amount' => 'decimal',
		'subtotal' => 'decimal',
		'contract_link' => 'url',
		'area_in_charge' => 'max:250',
		'contract_updated_at' => 'date_format:Y-m-d',
		'information_date' => 'date_format:Y-m-d',
		'amount_was_exceeded' => 'boolean',
		'exceeded_amount' => 'decimal',
		'contract_backup' => 'url',
	];

	public function __construct() {
		global $_payload;
		$aditionalPayload = [];
		if($_payload && isset($_payload->name)) {
			$aditionalPayload['slug'] = toAlphanumeric($_payload->name, '-');
		}
		parent::__construct($aditionalPayload);
	}

}
?>
