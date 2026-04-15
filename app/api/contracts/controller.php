<?php
use App\Model\BaseModel;
use App\Model\Crud;

class contractsComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'id','readonly' => true, 'saved' => false],
		'procedure_id' => ['field' => 'procedure_id'],
		'subject_id' => ['field' => 'subject_id'],
		'admin_period_start' => ['field' => 'admin_period_start'],
		'admin_period_end' => ['field' => 'admin_period_end'],
		'fiscal_year' => ['field' => 'fiscal_year'],
		'period_id' => ['field' => 'period_id'],
		'contract_id' => ['field' => 'contract_id'],
		'partida_type' => ['field' => 'partida_type'],
		'partida_id' => ['field' => 'partida_id'],
		'status_id' => ['field' => 'status_id'],
		'call_link' => ['field' => 'call_link'],
		'call_date' => ['field' => 'call_date'],
		'work_description' => ['field' => 'work_description'],
		'clarification_meeting_date' => ['field' => 'clarification_meeting_date'],
		'proposals_url' => ['field' => 'proposals_url'],
		'proposal_url' => ['field' => 'proposal_url'],
		'provider_id' => ['field' => 'provider_id'],
		'organizer_admin_unit_id' => ['field' => 'organizer_admin_unit_id'],
		'applicant_admin_unit_id' => ['field' => 'applicant_admin_unit_id'],
		'admin_unit_type_id' => ['field' => 'admin_unit_type_id'],
		'contract_number' => ['field' => 'contract_number'],
		'contract_date' => ['field' => 'contract_date'],
		'contract_type_id' => ['field' => 'contract_type_id'],
		'total_amount' => ['field' => 'total_amount', 'default' => 0],
		'min_amount' => ['field' => 'min_amount', 'default' => 0],
		'max_amount' => ['field' => 'max_amount', 'default' => 0],
		'subtotal' => ['field' => 'subtotal', 'default' => 0],
		'contract_link' => ['field' => 'contract_link'],
		'area_in_charge' => ['field' => 'area_in_charge'],
		'contract_updated_at' => ['field' => 'contract_updated_at'],
		'notes' => ['field' => 'notes'],
		'organization_notes' => ['field' => 'organization_notes'],
		'information_date' => ['field' => 'information_date'],
		'amount_was_exceeded' => ['field' => 'amount_was_exceeded', 'default' => 0],
		'amount_exceeded' => ['field' => 'amount_exceeded', 'default' => 0],
		'contract_backup' => ['field' => 'contract_backup'],
		'announcement_backup' => ['field' => 'announcement_backup'],
		'created_at' => ['field' => 'created_at', 'readonly' => true, 'saved' => false],
		'updated_at' => ['field' => 'updated_at', 'readonly' => true, 'saved' => false]
	];

	protected $get_params = [
		'table' => 'contratos c',
		'filters' => [],
		'joins' => [
			[
				'table' => 'c_procedures p',
				'match' => ['c.procedure_id', 'p.id'],
			],[
				'table' => 'c_materia m',
				'match' => ['c.subject_id', 'm.id'],
			],
			[
				'table' => 'c_partidas pt',
				'match' => ['c.partida_type_id', 'pt.id'],
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
		'amount_exceeded' => 'decimal',
		'contract_backup' => 'url',
		'announcement_backup' => 'url'
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
