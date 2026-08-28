<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     tool_dynamic_cohorts
 * @category    string
 * @copyright   2026 Anderson Blaine
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['add_rule'] = 'Adicionar nova regra';
$string['addcondition'] = 'Adicionar uma condição';
$string['addrule'] = 'Adicionar uma nova regra';
$string['after'] = 'Depois de';
$string['any'] = 'Qualquer';
$string['before'] = 'Antes de';
$string['broken'] = 'Quebrada';
$string['brokenruleswarning'] = 'Existem regras quebradas que exigem sua atenção. <br /> Para corrigir uma regra quebrada, remova todas as condições quebradas. <br />Às vezes uma regra fica quebrada porque o SQL de correspondência de usuários falhou. Nesse caso todas as condições estão corretas, mas a regra é marcada como quebrada. Consulte os logs do Moodle procurando pelo evento "Falha na correspondência de usuários" e pelos erros de SQL relacionados. <br />Observe que, em qualquer caso, você precisa salvar a regra novamente para marcá-la como não quebrada.';
$string['bulkprocessing'] = 'Processamento em lote';
$string['bulkprocessing_help'] = 'Se esta opção estiver habilitada, os usuários serão adicionados e removidos do coorte em lote. Isso melhora significativamente o desempenho do processamento. No entanto, usar esta opção suprime o disparo de eventos quando usuários são adicionados ou removidos do coorte.';
$string['cachedef_conditionrecords'] = 'Condições de uma regra';
$string['cachedef_matchinguserscount'] = 'Quantidade de usuários correspondentes a uma regra';
$string['cachedef_rulesconditions'] = 'Regras com uma condição específica';
$string['cannotenablebrokenrule'] = 'Uma regra quebrada não pode ser habilitada';
$string['cf_include_missing_data'] = 'Incluir coortes sem dados preenchidos.';
$string['cf_include_missing_data_help'] = 'Alguns coortes podem ainda não ter valor definido para um campo personalizado. Esta opção inclui esses coortes no resultado final.';
$string['cf_includingmissingdatadesc'] = '(incluindo coortes sem dados preenchidos)';
$string['cohort'] = 'Coorte';
$string['cohortid'] = 'Coorte';
$string['cohortid_help'] = 'O coorte a ser gerenciado por esta regra. Somente coortes que não são gerenciados por outros plugins aparecem nesta lista.';
$string['cohortswith'] = 'Coorte(s) com o campo';
$string['completed:add'] = 'A regra foi adicionada';
$string['completed:delete'] = 'A regra foi excluída';
$string['completed:disable'] = 'A regra foi desabilitada';
$string['completed:enable'] = 'A regra foi habilitada';
$string['completed:update'] = 'A regra foi atualizada';
$string['completiondate'] = 'Data de conclusão';
$string['completionisdisabled'] = 'A conclusão está desabilitada no curso configurado';
$string['condition'] = 'Condição';
$string['condition:auth_method'] = 'Método de autenticação';
$string['condition:cohort_field'] = 'Campo do coorte';
$string['condition:cohort_field_description'] = 'Usuários que {$a->operator} coortes com o campo \'{$a->field}\' {$a->fieldoperator} {$a->fieldvalue}';
$string['condition:cohort_membership'] = 'Participação em coorte';
$string['condition:cohort_membership_broken_description'] = 'A condição está quebrada. Ela usa o mesmo coorte que a regra está configurada para gerenciar.';
$string['condition:cohort_membership_description'] = 'Usuários que {$a->operator} {$a->cohorts}';
$string['condition:course_completed'] = 'Curso concluído';
$string['condition:course_completed_description'] = 'Usuários que concluíram o curso "{$a->course}" {$a->operator} {$a->timecompleted}';
$string['condition:course_not_completed'] = 'Curso não concluído';
$string['condition:course_not_completed_description'] = 'Usuários que não concluíram o curso "{$a->course}"';
$string['condition:profile_field_description'] = 'Usuários com {$a->field} {$a->fieldoperator} {$a->fieldvalue}';
$string['condition:user_created'] = 'Data de criação do usuário';
$string['condition:user_custom_profile'] = 'Campo de perfil personalizado do usuário';
$string['condition:user_enrolment'] = 'Inscrição do usuário';
$string['condition:user_enrolment_description'] = 'Usuários que estão {$a->operator} no curso "{$a->coursename}" (id {$a->courseid}) com o papel "{$a->role}" pelo método de inscrição "{$a->enrolmethod}"';
$string['condition:user_last_login'] = 'Último acesso do usuário';
$string['condition:user_profile'] = 'Campo padrão do perfil do usuário';
$string['condition:user_profile_interests'] = 'Interesses do usuário';
$string['condition:user_profile_interests_description'] = 'Usuários com interesses que contêm as seguintes tags {$a}';
$string['condition:user_profile_interests_description_not'] = 'Usuários com interesses que não contêm as seguintes tags {$a}';
$string['condition:user_role'] = 'Papel do usuário';
$string['condition:user_role_description_category'] = 'Usuários que {$a->operator} "{$a->role}" na categoria {$a->categoryname} (id {$a->categoryid})';
$string['condition:user_role_description_course'] = 'Usuários que {$a->operator} "{$a->role}" no curso {$a->coursename} (id {$a->courseid})';
$string['condition:user_role_description_system'] = 'Usuários que {$a->operator} "{$a->role}" no contexto do sistema';
$string['conditionchangesnotapplied'] = 'As alterações nas condições só são aplicadas quando você salvar o formulário da regra';
$string['conditionformtitle'] = 'Condição da regra';
$string['conditions'] = 'Condições';
$string['conditionsformtitle'] = 'Condições da regra';
$string['conditionstext'] = '{$a->conditions} ( lógica {$a->operator} )';
$string['delete_confirm'] = 'Tem certeza de que deseja excluir a regra?';
$string['delete_confirm_condition'] = 'Tem certeza de que deseja excluir esta condição?';
$string['delete_rule'] = 'Excluir regra';
$string['description'] = 'Descrição';
$string['description_help'] = 'Uma breve descrição desta regra';
$string['disable_confirm'] = 'Tem certeza de que deseja desabilitar a regra?';
$string['disabled'] = 'Desabilitada';
$string['donothaverole'] = 'não têm o papel';
$string['dynamic_cohorts:manage'] = 'Gerenciar regras';
$string['edit_rule'] = 'Editar regra';
$string['enable_confirm'] = 'Tem certeza de que deseja habilitar a regra?';
$string['enabled'] = 'Habilitada';
$string['enrolled'] = 'Inscritos';
$string['enrolmethod'] = 'Método de inscrição';
$string['event:conditioncreated'] = 'Condição criada';
$string['event:conditiondeleted'] = 'Condição excluída';
$string['event:conditionupdated'] = 'Condição atualizada';
$string['event:matchingfailed'] = 'Falha na correspondência de usuários';
$string['event:rulecreated'] = 'Regra criada';
$string['event:ruledeleted'] = 'Regra excluída';
$string['event:ruleupdated'] = 'Regra atualizada';
$string['ever'] = 'Alguma vez';
$string['everloggedin'] = 'Usuários que acessaram pelo menos uma vez';
$string['haverole'] = 'têm o papel';
$string['include_missing_data'] = 'Incluir usuários sem dados preenchidos.';
$string['include_missing_data_help'] = 'Alguns usuários podem ainda não ter valor definido para um campo personalizado. Esta opção inclui esses usuários no resultado final.';
$string['includechildren'] = 'incluindo os filhos (categorias e cursos)';
$string['includeusersmissingdata'] = 'incluir usuários sem dados preenchidos';
$string['includingmissingdatadesc'] = '(incluindo usuários sem dados preenchidos)';
$string['inlast'] = 'Nos últimos';
$string['inlastloggedin'] = 'Usuários que acessaram nos últimos {$a}';
$string['inthefuture'] = 'está no futuro';
$string['inthepast'] = 'está no passado';
$string['invalidfieldvalue'] = 'Valor de campo inválido';
$string['isafter'] = 'é depois de';
$string['isbefore'] = 'é antes de';
$string['ismemberof'] = 'são membros de';
$string['isnotempty'] = 'não está vazio';
$string['isnotmemberof'] = 'não são membros de';
$string['loggedintime'] = 'Usuários que acessaram {$a->operator} {$a->time}';
$string['logical_operator'] = 'Operador lógico';
$string['logical_operator_help'] = 'O operador lógico a ser aplicado às condições desta regra. O operador "AND" significa que o usuário precisa atender a todas as condições para ser adicionado ao coorte. "OR" significa que basta atender a qualquer uma das condições.';
$string['managecohorts'] = 'Gerenciar coortes';
$string['managerules'] = 'Gerenciar regras';
$string['matchingusers'] = 'Usuários correspondentes';
$string['missingcourse'] = 'Curso ausente';
$string['missingcoursecat'] = 'Categoria de curso ausente';
$string['missingenrolmentmethod'] = 'Método de inscrição ausente {$a}';
$string['missingrole'] = 'Papel ausente';
$string['missingtag'] = 'Tag ausente {$a}';
$string['name'] = 'Nome da regra';
$string['name_help'] = 'Um nome legível para esta regra.';
$string['never'] = 'Nunca';
$string['neverloggedin'] = 'Usuários que nunca acessaram';
$string['notenrolled'] = 'Não inscritos';
$string['operator'] = 'Operador';
$string['or'] = 'OU';
$string['pleaseselectcohort'] = 'Selecione um coorte';
$string['pleaseselectfield'] = 'Selecione um campo';
$string['pluginname'] = 'Coortes dinâmicos';
$string['privacy:metadata:tool_dynamic_cohorts'] = 'Informações sobre regras criadas ou atualizadas por um usuário';
$string['privacy:metadata:tool_dynamic_cohorts:name'] = 'Nome da regra';
$string['privacy:metadata:tool_dynamic_cohorts:usermodified'] = 'O ID do usuário que criou ou atualizou uma regra';
$string['privacy:metadata:tool_dynamic_cohorts_c'] = 'Informações sobre condições criadas ou atualizadas por um usuário';
$string['privacy:metadata:tool_dynamic_cohorts_c:ruleid'] = 'ID da regra';
$string['privacy:metadata:tool_dynamic_cohorts_c:usermodified'] = 'O ID do usuário que criou ou atualizou uma condição';
$string['processrulestask'] = 'Processar regras de coortes dinâmicos';
$string['profilefield'] = 'Campo do perfil';
$string['realtime'] = 'Processamento em tempo real';
$string['realtime_help'] = 'Se habilitado, a regra é processada de forma síncrona como parte do evento (quando as condições permitem o disparo pelo evento). Use com cautela: o processamento demorado de uma regra bloqueia a interface do usuário.';
$string['realtimedisabledglobally'] = 'Processamento em tempo real desabilitado globalmente';
$string['rule_entity'] = 'Regra de coorte dinâmico';
$string['rule_entity.bulkprocessing'] = 'Processamento em lote';
$string['rule_entity.description'] = 'Descrição';
$string['rule_entity.id'] = 'ID';
$string['rule_entity.name'] = 'Nome';
$string['rule_entity.realtime'] = 'Processamento em tempo real';
$string['rule_entity.status'] = 'Situação';
$string['ruledisabledpleasereview'] = 'Regras recém-criadas ou atualizadas ficam desabilitadas por padrão. Revise a regra abaixo e habilite-a quando estiver pronta.';
$string['settings:realtime'] = 'Processamento em tempo real';
$string['settings:realtime_desc'] = 'Quando habilitado, regras com condições que permitem o disparo pelo evento são processadas de forma síncrona como parte do evento. Use com cautela: o processamento demorado de uma regra bloqueia a interface do usuário.';
$string['settings:releasemembers'] = 'Liberar membros';
$string['settings:releasemembers_desc'] = 'Se habilitado, todos os membros são removidos do coorte assim que ele deixa de ser gerenciado pelo plugin (por exemplo, quando a regra é excluída ou o coorte da regra é alterado). <br/> Observe: nenhum evento cohort_member_removed é disparado quando os membros são liberados do coorte. Caso contrário, a regra é processada via cron.';
$string['task_process_rule'] = 'Processar uma regra de coorte dinâmico';
$string['usercreated'] = 'O usuário foi criado';
$string['usercreatedin'] = 'Usuários criados nos últimos {$a}';
$string['usercreatedtime'] = 'Usuários criados {$a->operator} {$a->time}';
$string['userlastlogin'] = 'Último acesso do usuário';
