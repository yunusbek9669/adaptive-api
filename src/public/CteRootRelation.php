<?php

namespace Yunusbek\AdaptiveApi\public;

use app\modules\employee\models\EmployeePunishment;
use app\modules\employee\models\EmployeeWork;
use app\modules\manuals\models\ManualsLaborLeaveAction;
use app\modules\manuals\models\ManualsLaborLeaveType;
use Exception;

class CteRootRelation
{
    public static function getCteRootRelation(string $cte, array $params): string
    {
        $cte_result = '';
        switch ($cte) {

            case CteConstants::FORM_EDUCATION:
                $cte_result = <<<SQL
                    WITH {$cte} AS (
                        SELECT 
                            ee.employee_id,
                            COALESCE(
                                jsonb_agg(
                                    jsonb_build_object(
                                        'informationType', jsonb_build_object(
                                            'name_latin', meit.name_uz,
                                            'name_kirill', meit.name_ru,
                                            'name_qoraqalpoq', COALESCE(NULLIF(meit.name_en, ''), meit.name_ru),
                                            'code', meit.code
                                        ),
                                        'educationDegree', CASE
                                            WHEN mesd.id IS NOT NULL THEN jsonb_build_object(
                                                    'name_latin', mesd.name_uz,
                                                    'name_kirill', mesd.name_ru,
                                                    'name_qoraqalpoq', COALESCE(NULLIF(mesd.name_en, ''), mesd.name_ru),
                                                    'code', mesd.code
                                                )
                                        END,
                                        'educationInstitution', CASE
                                            WHEN mei.id IS NOT NULL THEN jsonb_build_object(
                                                    'name_latin', mei.name_uz,
                                                    'name_kirill', mei.name_ru,
                                                    'name_qoraqalpoq', COALESCE(NULLIF(mei.name_en, ''), mei.name_ru),
                                                    'code', mei.code
                                                )
                                            ELSE jsonb_build_object(
                                                    'name_latin', CONCAT('{uz}',ee.institution_direction_name),
                                                    'name_kirill', CONCAT('{ru}',ee.institution_direction_name),
                                                    'name_qoraqalpoq', CONCAT('{en}',ee.institution_direction_name),
                                                    'code', null
                                                )
                                        END,
                                        'educationClassification', CASE
                                            WHEN meis.id IS NOT NULL THEN jsonb_build_object(
                                                    'name_latin', meis.name_uz,
                                                    'name_kirill', meis.name_ru,
                                                    'name_qoraqalpoq', COALESCE(NULLIF(meis.name_en, ''), meis.name_ru),
                                                    'code', meis.code
                                                )
                                            WHEN NULLIF(ee.institution_specialization_name, '') IS NOT NULL THEN jsonb_build_object(
                                                    'name_latin', CONCAT('{uz}',ee.institution_specialization_name),
                                                    'name_kirill', CONCAT('{ru}',ee.institution_specialization_name),
                                                    'name_qoraqalpoq', CONCAT('{en}',ee.institution_specialization_name),
                                                    'code', null
                                                )
                                        END,
                                        'educationDirection', CASE
                                            WHEN mesd.id IS NOT NULL AND mei.id IS NOT NULL THEN jsonb_build_object(
                                                    'name_latin', CONCAT('{uz}',ee.institution_direction_name),
                                                    'name_kirill', CONCAT('{ru}',ee.institution_direction_name),
                                                    'name_qoraqalpoq', CONCAT('{en}',ee.institution_direction_name)
                                                )
                                        END
                                    )
                                ),
                                jsonb_build_array()
                            ) AS education
                        FROM cte_root_table crt
                        JOIN employee_education ee ON ee.employee_id = crt.employee_id AND ee.status = :status AND ee.status_active = :status AND ee.education_status = {$params['education_status']}
                        LEFT JOIN manuals_educational_information_type meit ON meit.id = ee.educational_information_type_id AND meit.status = :status
                        LEFT JOIN manuals_education_specialization_degree mesd ON mesd.id = ee.education_specialization_degree_id AND mesd.status = :status
                        LEFT JOIN manuals_education_institution mei ON mei.id = ee.education_institution_id AND mei.status = :status
                        LEFT JOIN manuals_education_institution_specialization meis ON meis.id = ee.education_institution_specialization_id AND meis.status = :status
                        GROUP BY ee.employee_id
                    )
                SQL;
                break;

            case CteConstants::FORM_LABOR_LEAVE:
                $action_code = var_export(ManualsLaborLeaveAction::EMPLOYEE_LABOR_LEAVE_GRANTED, true);
                $type_code = var_export(ManualsLaborLeaveType::STUDY_CADET, true);
                $cte_result = <<<SQL
                    WITH {$cte} AS (
                        SELECT
                            crt.employee_id,
                            mllt.short_name_uz AS name_latin,
                            mllt.short_name_ru AS name_kirill,
                            mllt.short_name_en AS name_qoraqalpoq,
                            mllt.code,
                            ell.adulthood_care_until_uz AS cause_name_latin,
                            ell.adulthood_care_until_ru AS cause_name_kirill,
                            ell.adulthood_care_until_en AS cause_name_qoraqalpoq,
                            ell.basis_name_uz AS basis_name_latin,
                            ell.basis_name_ru AS basis_name_kirill,
                            ell.basis_name_en AS basis_name_qoraqalpoq,
                            COALESCE(TO_CHAR(TO_TIMESTAMP(ell.begin_date), 'DD.MM.YYYY'), null) AS begin_date,
                            COALESCE(TO_CHAR(TO_TIMESTAMP(ell.end_date), 'DD.MM.YYYY'), null) AS end_date
                        FROM cte_root_table crt
                        JOIN employee_labor_leave ell ON ell.employee_id = crt.employee_id AND ell.status = :status AND ell.status_active = :status AND ell.begin_date <= EXTRACT(EPOCH FROM CURRENT_DATE)::bigint AND ell.end_date >= EXTRACT(EPOCH FROM CURRENT_DATE)::bigint
                        JOIN manuals_labor_leave_action mlla ON mlla.id = ell.labor_leave_action_id AND mlla.status = :status AND mlla.code = {$action_code}
                        LEFT JOIN manuals_labor_leave_type mllt ON mllt.id = ell.labor_leave_type_id AND mllt.status = :status AND mllt.code = {$type_code}
                    )
                SQL;
                break;

            case 'cte_diagnostic':
                $cte_result = <<<SQL
                    WITH {$cte} AS (
                        SELECT
                            crt.employee_id,
                            ed.helth,
                            ed.weight,
                            ed.medical_control_direction_uz AS name_latin,
                            ed.medical_control_direction_ru AS name_kirill,
                            ed.medical_control_direction_en AS name_qoraqalpoq,
                            ed.basis_name_uz AS basis_name_latin,
                            ed.basis_name_ru AS basis_name_kirill,
                            ed.basis_name_en AS basis_name_qoraqalpoq,
                            COALESCE(TO_CHAR(TO_TIMESTAMP(ed.medical_control_date), 'DD.MM.YYYY'), null) AS medical_control_date,
                            COALESCE(TO_CHAR(TO_TIMESTAMP(ed.diagnostic_date), 'DD.MM.YYYY'), null) AS diagnostic_date
                        FROM cte_root_table crt
                        JOIN employee_diagnostic ed ON ed.employee_id = crt.employee_id AND ed.status = :status AND ed.status_active = :status
                        
                    )
                SQL;
                break;
        }
        return $cte_result;
    }
}