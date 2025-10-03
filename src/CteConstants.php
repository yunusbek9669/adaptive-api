<?php

namespace Yunusbek\AdaptiveApi;

class CteConstants
{
    public const CTE_ROOT_SCHEMA_PATH = '/uploads/adaptive-api/';
    public const CTE_ROOT = 'cteRoot';
    public const CTE_ROOT_LIMITED = 'limitedRoot';
    public const ROOT_RELATION_DATA_TYPE = 'rootRelationDataTypes';
    public const REFERENCE_DATA_TYPE = 'referenceDataTypes';

    /** Xodimlar uchun  */
    public const FORM_STATUS = 'status';
    public const FORM_PERSONAL = 'personal';
    public const FORM_BIRTH_ADDRESS = 'birthAddress';
    public const FORM_CURRENT_ADDRESS = 'currentAddress';
    public const FORM_CONSTANT_ADDRESS = 'constantAddress';
    public const FORM_EMPLOYEE_TYPE = 'employeeType';
    public const FORM_GENDER = 'gender';
    public const FORM_MARITAL_STATUS = 'maritalStatus';
    public const FORM_PASSPORT = 'passport';
    public const FORM_NATIONALITY = 'nationality';
    public const FORM_CITIZENSHIP = 'citizenship';
    public const FORM_EDUCATION = 'education';
    public const FORM_ORGANIZATION = 'organization';
    public const FORM_DEPARTMENT = 'department';
    public const FORM_POSITION_TYPE = 'positionType';
    public const FORM_POSITION = 'position';
    public const FORM_INSPECTOR_QUARTER = 'inspectorQuarter';
    public const FORM_MILITARY_DEGREE = 'militaryDegree';
    public const FORM_JETON = 'jeton';
    public const FORM_MILITARY_CERTIFICATE = 'militaryCertificate';
    public const FORM_PUNISHMENT = 'punishment';
    public const FORM_LABOR_LEAVE = 'laborLeave';
    public const FORM_MILITARY_PHOTO = 'militaryPhoto';
    public const FORM_PASSPORT_PHOTO = 'passportPhoto';

    /** Reference */
    public const REF_EMPLOYEE_SYSTEM_TYPE = "refEmployeeSystemType";
    public const REF_STATE = "refState";
    public const REF_REGION = "refRegion";
    public const REF_DISTRICT = "refDistrict";
    public const REF_QUARTER = "refQuarter";
    public const REF_GENDER = "refGender";
    public const REF_LANGUAGE = "refLanguage";
    public const REF_LANGUAGE_STATUS = "refLanguageStatus";
    public const REF_FAMILY_MEMBERS = "refFamilyMembers";
    public const REF_PARTY_MEMBERSHIP = "refPartyMembership";
    public const REF_CERTIFICATE_SERIES = "refCertificateSeries";
    public const REF_MARITAL_STATUS = "refMaritalStatus";
    public const REF_PASSPORT_TYPE = "refPassportType";
    public const REF_PASSPORT_SERIAL = "refPassportSerial";
    public const REF_NATIONALITY = "refNationality";
    public const REF_CITIZENSHIP = "refCitizenship";
    public const REF_ORGANIZATION = "refOrganization";
    public const REF_DEPARTMENT = "refDepartment";
    public const REF_DEPARTMENT_TYPE = "refDepartmentType";
    public const REF_DEPARTMENT_RELEVANT_TYPE = "refDepartmentRelevantType";
    public const REF_POSITION_RELEVANT_TYPE = "refPositionRelevantType";
    public const REF_DEPARTMENT_SOCIAL_SERVICE = "refDepartmentSocialService";
    public const REF_POSITION = "refPosition";
    public const REF_POSITION_CATEGORY = "refPositionCategory";
    public const REF_POSITION_CHIEF = "refPositionChief";
    public const REF_POSITION_COEFFICIENT = "refPositionCoefficient";
    public const REF_POSITION_TYPE = "refPositionType";
    public const REF_COLLATERAL_TYPE = "refCollateralType";
    public const REF_MILITARY_DEGREE = "refMilitaryDegree";
    public const REF_MILITARY_DEGREE_TYPE = "refMilitaryDegreeType";
    public const REF_MILITARY_DEGREE_REASON = "refMilitaryDegreeReason";
    public const REF_MILITARY_DEGREE_STRUCTURE = "refMilitaryDegreeStructure";
    public const REF_MILITARY_TICKET_TYPE = "refMilitaryTicketType";
    public const REF_MILITARY_DEGREE_ACTION_TYPE = "refMilitaryDegreeActionType";
    public const REF_WORK_EXPERIENCE_ACTION_TYPE = "refWorkExperienceActionType";
    public const REF_PEDAGOGICAL_EXPERIENCE_ACTION_TYPE = "refPedagogicalExperienceActionType";
    public const REF_COMMAND_ACTION_TYPE = "refCommandActionType";
    public const REF_COMMON_COMMAND_TYPE = "refCommonCommandType";
    public const REF_COMMAND_TYPE = "refCommandType";
    public const REF_CATEGORY_COMMAND_TYPE = "refCategoryCommandType";
    public const REF_ACADEMIC_DEGREE_TYPE = "refAcademicDegreeType";
    public const REF_ACADEMIC_DEGREE = "refAcademicDegree";
    public const REF_ACADEMIC_TITLE_TYPE = "refAcademicTitleType";
    public const REF_ACADEMIC_TITLE = "refAcademicTitle";
    public const REF_AWARDS_TYPE = "refAwardsType";
    public const REF_DOCTOR_POSITION_CATEGORY_TYPE = "refDoctorPositionCategoryType";
    public const REF_JETON_SERIAL = "refJetonSerial";
    public const REF_EMPLOYEE_ACTION_TYPE = "refEmployeeActionType";
    public const REF_EMPLOYEE_AGE_TYPE = "refEmployeeAgeType";
    public const REF_EMPLOYEE_ARCHIVE_TYPE = "refEmployeeArchiveType";
    public const REF_EMPLOYEE_ATTESTATION = "refEmployeeAttestation";
    public const REF_EMPLOYEE_CATEGORY = "refEmployeeCategory";
    public const REF_EMPLOYEE_CATEGORY_TYPE = "refEmployeeCategoryType";
    public const REF_EMPLOYEE_DISMISSAL = "refEmployeeDismissal";
    public const REF_EMPLOYEE_DISMISSAL_TYPE = "refEmployeeDismissalType";
    public const REF_EMPLOYEE_ENCOURAGE_ACTION_TYPE = "refEmployeeEncourageActionType";
    public const REF_EMPLOYEE_PERMIT_TYPE = "refEmployeePermitType";
    public const REF_PUNISHMENT = "refPunishment";
    public const REF_EMPLOYEE_REASON_DELETION = "refEmployeeReasonDeletion";
    public const REF_EMPLOYEE_STATES_REDUCTION_TYPE = "refEmployeeStatesReductionType";
    public const REF_EMPLOYEE_TYPE = "refEmployeeType";
    public const REF_EMPLOYEE_CONTRACTION = "refEmployeeContraction";
    public const REF_EMPLOYEE_WORK_TYPE = "refEmployeeWorkType";
    public const REF_QUARTER_ATTACHMENT_TYPE = "refQuarterAttachmentType";
    public const REF_ENCOURAGE = "refEncourage";
    public const REF_GARRISON_GUARDHOUSE = "refGarrisonGuardhouse";
    public const REF_INJURIES_TYPE = "refInjuriesType";
    public const REF_LABOR_LEAVE_TYPE = "refLaborLeaveType";
    public const REF_LABOR_LEAVE_ACTION = "refLaborLeaveAction";
    public const REF_WORK_ACTION = "refWorkAction";
    public const REF_ACADEMIC_ACTION = "refAcademicAction";
    public const REF_BLOOD_GROUP = "refBloodGroup";
    public const REF_HEALTH_TYPE = "refHealthType";
    public const REF_HEALTH_LEVEL_TYPE = "refHealthLevelType";
    public const REF_MEDICAL_ACCOUNT = "refMedicalAccount";
    public const REF_ORGANIZATION_AWARDS = "refOrganizationAwards";
    public const REF_PERCENTAGE_SURCHARGE_TYPE = "refPercentageSurchargeType";
    public const REF_STATE_AWARDS = "refStateAwards";
    public const REF_STATE_AWARDS_TYPE = "refStateAwardsType";
    public const REF_EDUCATIONAL_INFORMATION_TYPE = "refEducationalInformationType";
    public const REF_EDUCATIONAL_INSTITUTION_TYPE = "refEducationalInstitutionType";
    public const REF_EDUCATIONAL_READING_TYPE = "refEducationalReadingType";
    public const REF_EDUCATIONAL_COMMAND_TYPE = "refEducationalCommandType";
    public const REF_EDUCATIONAL_COMMAND_COURSE = "refEducationalCommandCourse";
    public const REF_EDUCATIONAL_INSTITUTION_STATUS = "refEducationalInstitutionStatus";
    public const REF_EDUCATIONAL_SPECIALIZATION_DEGREE = "refEducationSpecializationDegree";
    public const REF_EDUCATIONAL_INSTITUTION = "refEducationInstitution";
    public const REF_EDUCATIONAL_QUALIFICATION = "refEducationQualification";

    public static function employeeDataTypeList(): array
    {
        return [
            self::FORM_STATUS,
            self::FORM_PERSONAL,
            self::FORM_ORGANIZATION,
            self::FORM_DEPARTMENT,
            self::FORM_PASSPORT,
            self::FORM_MILITARY_CERTIFICATE,
            self::FORM_MILITARY_DEGREE,
            self::FORM_JETON,
            self::FORM_CITIZENSHIP,
            self::FORM_NATIONALITY,
            self::FORM_MARITAL_STATUS,
            self::FORM_GENDER,
            self::FORM_EMPLOYEE_TYPE,
            self::FORM_PASSPORT_PHOTO,
            self::FORM_MILITARY_PHOTO,
            self::FORM_EDUCATION,
            self::FORM_POSITION_TYPE,
            self::FORM_POSITION,
            self::FORM_INSPECTOR_QUARTER,
            self::FORM_PUNISHMENT,
            self::FORM_LABOR_LEAVE,
            self::FORM_BIRTH_ADDRESS,
            self::FORM_CURRENT_ADDRESS,
            self::FORM_CONSTANT_ADDRESS,
        ];
    }

    public static function referenceDataTypeList(): array
    {
        return [
            self::REF_EMPLOYEE_SYSTEM_TYPE,
            self::REF_STATE,
            self::REF_REGION,
            self::REF_DISTRICT,
            self::REF_QUARTER,
            self::REF_GENDER,
            self::REF_LANGUAGE,
            self::REF_LANGUAGE_STATUS,
            self::REF_FAMILY_MEMBERS,
            self::REF_PARTY_MEMBERSHIP,
            self::REF_CERTIFICATE_SERIES,
            self::REF_MARITAL_STATUS,
            self::REF_PASSPORT_TYPE,
            self::REF_PASSPORT_SERIAL,
            self::REF_NATIONALITY,
            self::REF_CITIZENSHIP,
            self::REF_ORGANIZATION,
            self::REF_DEPARTMENT,
            self::REF_DEPARTMENT_TYPE,
            self::REF_DEPARTMENT_RELEVANT_TYPE,
            self::REF_POSITION_RELEVANT_TYPE,
            self::REF_DEPARTMENT_SOCIAL_SERVICE,
            self::REF_POSITION,
            self::REF_POSITION_CATEGORY,
            self::REF_POSITION_CHIEF,
            self::REF_POSITION_COEFFICIENT,
            self::REF_POSITION_TYPE,
            self::REF_COLLATERAL_TYPE,
            self::REF_MILITARY_DEGREE,
            self::REF_MILITARY_DEGREE_TYPE,
            self::REF_MILITARY_DEGREE_REASON,
            self::REF_MILITARY_DEGREE_STRUCTURE,
            self::REF_MILITARY_TICKET_TYPE,
            self::REF_MILITARY_DEGREE_ACTION_TYPE,
            self::REF_WORK_EXPERIENCE_ACTION_TYPE,
            self::REF_PEDAGOGICAL_EXPERIENCE_ACTION_TYPE,
            self::REF_COMMAND_ACTION_TYPE,
            self::REF_COMMAND_TYPE,
            self::REF_CATEGORY_COMMAND_TYPE,
            self::REF_ACADEMIC_DEGREE,
            self::REF_ACADEMIC_DEGREE_TYPE,
            self::REF_ACADEMIC_TITLE,
            self::REF_ACADEMIC_TITLE_TYPE,
            self::REF_AWARDS_TYPE,
            self::REF_DOCTOR_POSITION_CATEGORY_TYPE,
            self::REF_JETON_SERIAL,
            self::REF_EMPLOYEE_ACTION_TYPE,
            self::REF_EMPLOYEE_AGE_TYPE,
            self::REF_EMPLOYEE_ARCHIVE_TYPE,
            self::REF_EMPLOYEE_ATTESTATION,
            self::REF_EMPLOYEE_CATEGORY,
            self::REF_EMPLOYEE_CATEGORY_TYPE,
            self::REF_EMPLOYEE_DISMISSAL,
            self::REF_EMPLOYEE_DISMISSAL_TYPE,
            self::REF_EMPLOYEE_ENCOURAGE_ACTION_TYPE,
            self::REF_EMPLOYEE_PERMIT_TYPE,
            self::REF_PUNISHMENT,
            self::REF_EMPLOYEE_REASON_DELETION,
            self::REF_EMPLOYEE_STATES_REDUCTION_TYPE,
            self::REF_EMPLOYEE_TYPE,
            self::REF_EMPLOYEE_CONTRACTION,
            self::REF_EMPLOYEE_WORK_TYPE,
            self::REF_QUARTER_ATTACHMENT_TYPE,
            self::REF_ENCOURAGE,
            self::REF_GARRISON_GUARDHOUSE,
            self::REF_INJURIES_TYPE,
            self::REF_LABOR_LEAVE_TYPE,
            self::REF_LABOR_LEAVE_ACTION,
            self::REF_WORK_ACTION,
            self::REF_ACADEMIC_ACTION,
            self::REF_BLOOD_GROUP,
            self::REF_HEALTH_TYPE,
            self::REF_HEALTH_LEVEL_TYPE,
            self::REF_MEDICAL_ACCOUNT,
            self::REF_ORGANIZATION_AWARDS,
            self::REF_STATE_AWARDS,
            self::REF_STATE_AWARDS_TYPE,
            self::REF_EDUCATIONAL_INFORMATION_TYPE,
            self::REF_EDUCATIONAL_INSTITUTION_TYPE,
            self::REF_EDUCATIONAL_READING_TYPE,
            self::REF_EDUCATIONAL_COMMAND_TYPE,
            self::REF_EDUCATIONAL_COMMAND_COURSE,
            self::REF_EDUCATIONAL_INSTITUTION_STATUS,
            self::REF_EDUCATIONAL_SPECIALIZATION_DEGREE,
            self::REF_EDUCATIONAL_INSTITUTION,
            self::REF_EDUCATIONAL_QUALIFICATION,
        ];
    }
}