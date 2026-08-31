<?php
// This file is part of "Moodle SGA Integration"
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
 * SGA Integration
 *
 * @package     tool_sga
 * @category    admin
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga\admin;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Access is restricted by is_siteadmin() below.
require_once(\dirname(\dirname(\dirname(__DIR__))) . '/config.php');

$PAGE->set_url(new \moodle_url('/admin/tool/sga/admin/index.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('SGA Sync Admin');


if (!is_siteadmin()) {
    echo $OUTPUT->header();
    echo "Fazes o quê aqui?";
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();

$ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : 'ASC';

// Número de itens por página.
$itensporpagina = 10;
$paginaatual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Consulta SQL personalizada para buscar os registros com LIMIT e OFFSET.
$sql = "
    SELECT id, timecreated, processed
    FROM {sga_enrolment_to_sync}
    ORDER BY id $ordenacao, timecreated $ordenacao, processed $ordenacao
    LIMIT :limit OFFSET :offset
";

$params = [
    'limit' => $itensporpagina,
    'offset' => ($paginaatual - 1) * $itensporpagina,
];
$registros = $DB->get_records_sql($sql, $params);

$statuses = [0 => "Não processado", 1 => "Sucesso", 2 => 'Falha'];
foreach ($registros as $key => $value) {
    $value->status = $statuses[$value->processed];
}

// Consulta SQL para contar o total de registros.
$sqltotalregistros = "SELECT COUNT(*) as total FROM {sga_enrolment_to_sync}";

$totalregistros = $DB->get_field_sql($sqltotalregistros);

$numerototaldepaginas = ceil($totalregistros / $itensporpagina);

$primeiraspaginas = 5;
$ultimaspaginas = 3;

$paginainicio = max(2, $paginaatual - floor($primeiraspaginas / 2));
$paginafim = $paginainicio + $primeiraspaginas - 1;

$registrospaginaatual = array_slice($registros, 0, $itensporpagina);

// Verifica o numero total de páginas com o range de paginação, para delimitar um fim para a
// paginação, caso outras páginas sejam clicadas.
if (in_array($numerototaldepaginas, range($paginainicio, $paginafim))) {
    $primeiroscinco = range($paginainicio, $numerototaldepaginas);
} else {
    $primeiroscinco = range($paginainicio, $paginafim);
}

$ultimostres = range($numerototaldepaginas, $numerototaldepaginas);

$paginacaovariada = [];

// Verifica se tem mais de 13 páginas. Se tiver, irá acrescentar a lógica de aparecer as 3 ultimas.
if ($numerototaldepaginas < $primeiraspaginas + $ultimaspaginas) {
    $paginacaovariada = range($paginainicio, $paginafim);
} else {
    if ($paginaatual < $numerototaldepaginas - 3 && $paginaatual >= 5) {
        echo ("TO AQUI");
        $mergeunique = array_unique(array_merge($primeiroscinco, ['...'], $ultimostres));
        $paginacaovariada = array_merge(['...'], $mergeunique);
    } else if ($paginaatual < $numerototaldepaginas - 3) {
        $mergeunique = array_unique(array_merge($primeiroscinco, ['...'], $ultimostres));
        $paginacaovariada = array_merge($mergeunique);
    } else if ($paginaatual >= 5) {
        $mergeunique = array_unique(array_merge($primeiroscinco, $ultimostres));
        $paginacaovariada = array_merge(['...'], $mergeunique);
    } else {
        $paginacaovariada = array_unique(array_merge($primeiroscinco, $ultimostres));
    }
}

$templatecontext = [
    'linhas' => $registrospaginaatual,
    'paginas' => $paginacaovariada,
];

echo $OUTPUT->render_from_template('tool_sga/index', $templatecontext);
echo $OUTPUT->footer();
