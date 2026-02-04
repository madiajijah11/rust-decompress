<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIT</title>
</head>

<body>
    <form action="#" method="get">
        <label for="user">ID User:</label>
        <input type="number" id="user" name="user">
        <label for="start">Start Date:</label>
        <input type="date" id="start" name="start">
        <label for="end">End Date:</label>
        <input type="date" id="end" name="end">
        <input type="submit" value="Submit">
    </form>
    <style>
        .kop {
            display: none;
        }

        @media print {
            form {
                display: none;
            }

            .kop {
                display: table;
                width: 100%;
            }
        }

        .center {
            text-align: center;
        }

        .no-border {
            border: none;
        }
    </style>

    <table border="0" width="100%" class="kop">
        <tr>
            <td style="vertical-align: middle; text-align: center;" rowspan='6'>
                <img src="assets/logos.png" alt="logo" width="70" height="75" />
            </td>
            <td style="text-align: center;">
                <span style="display: inline-block; text-indent: -270px;"><b>PEMERINTAH PROVINSI KALIMANTAN UTARA</b></span>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; text-indent: -270px;"><b>DINAS KESEHATAN</b></span>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; text-indent: -270px;"><b>RSUD dr. H. JUSUF SK</b></span>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; text-indent: -270px;">Jl. Pulau Irian No. 1 Kode Pos 77113 Telp. (0551) 21166 Fax (0551) 21270</span>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; text-indent: -270px;">Email: rsuddrhjusufsk@kaltaraprov.go.id ; Website: rsuddrhjusufsk.kaltaraprov.go.id</span>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <span style="display: inline-block; text-indent: -270px;"><b>T A R A K A N</b></span>
            </td>
        </tr>
        <tr>
            <td colspan='2'>
                <hr style="border:0.4px double #000">
                <hr style="border:1px double #000; margin-top: -14px">
            </td>
        </tr>
    </table>

</body>

</html>
<?php

function makeCurlRequest($url, $headers = [])
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HEADER => true, // Ambil header response
        CURLOPT_HTTPHEADER => array_merge([
            'Private-Token: -',
        ], $headers),
    ));

    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($curl);

    return ['header' => $header, 'body' => $body];
}

// Ambil semua halaman issues dengan pagination
function getAllIssues($projectId)
{
    $perPage = 100;
    $page = 1;
    $issues = [];

    do {
        $url = "http://172.168.1.33/api/v4/projects/$projectId/issues?per_page=$perPage&page=$page";
        $response = makeCurlRequest($url);

        // Ambil header untuk cek X-Total-Pages
        preg_match('/X-Total-Pages: (\d+)/', $response['header'], $matches);
        $totalPages = isset($matches[1]) ? (int)$matches[1] : 1;

        // Decode response body
        $issuesPage = json_decode($response['body'], true);
        $issues = array_merge($issues, $issuesPage);

        $page++;
    } while ($page <= $totalPages);

    return $issues;
}

// Ambil semua issues dari project ID 2
$allIssues = getAllIssues(2);
if (isset($_GET['start']) && isset($_GET['end']) && isset($_GET['user'])) {
    $user = $_GET['user'];
    $start = $_GET['start'];
    $end = $_GET['end'];
    $start = date('Y-m-d', strtotime($start . ' -1 day'));
    $end = date('Y-m-d', strtotime($end . ' +1 day'));
    // Ambil semua event user dalam rentang tanggal
    $response = makeCurlRequest('http://172.168.1.33/api/v4/users/' . $user . '/events?after=' . $start . '&before=' . $end);
    $res = json_decode($response['body'], true);
    // var_dump($res);die;
    if (isset($res['error'])) {
        echo "Failed to get data";
        exit;
    }
    foreach ($res as $key => $value) {
        if (isset($value['push_data'])) {
            $commitSha = $value['push_data']['commit_to'];
            $branch = $value['push_data']['ref'];
        } else {
            $commitSha = '';
            $branch = '';
        }

        // Ambil detail commit dan diff
        $response1 = makeCurlRequest("http://172.168.1.33/api/v4/projects/1/repository/commits/$commitSha");
        $res1 = json_decode($response1['body'], true);

        $response2 = makeCurlRequest("http://172.168.1.33/api/v4/projects/1/repository/commits/$commitSha/diff");
        $res2 = json_decode($response2['body'], true);

        // Cek apakah ada issue terkait berdasarkan branch/ref
        foreach ($allIssues as $issue) {
            if (isset($issue['description']) && strpos($issue['description'], $branch) !== false && $branch != '') {
                $res[$key]['issues'] = $issue['title'];
            }
        }
        $res[$key]['commit'] = $res1;
        $res[$key]['diff'] = $res2;
    }

    // Tampilkan dalam tabel HTML
    echo "<style>
        table {
            border-collapse: collapse;
            width: 100%; /* Agar tabel mengambil seluruh lebar container */
            table-layout: auto; /* Biarkan browser menyesuaikan lebar kolom */
            word-wrap: break-word; /* Memastikan teks dalam sel bisa dipotong */
        }
        .solid {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        pre {
            white-space: pre-wrap; /* Agar teks dalam <pre> juga mengikuti lebar */
            word-wrap: break-word;
        }
      </style>";
    $months = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];
    echo "<center><h4>Laporan Bulan " . $months[date('m', strtotime($_GET['start']))] . " " . date('Y', strtotime($_GET['start'])) . "</h4></center>";
    if (count($res) > 0) {
        echo "<center><h4>" . $res[0]['author']['name'] . ", S.Kom" . ($user == 4 ? " -199502222022031005" : "") . "</h4></center>";
    }
    echo "<center><h4>SIMRS</h4></center>";
    echo "<table>";
    echo "<tr>
            <th class='solid'>ID</th>
            <th class='solid'>Issues</th>
            <th class='solid'>Branch</th>
            <th class='solid'>Commit</th>
            <th class='solid'>Progress</th>
            <th class='solid'>Tanggal</th>
        </tr>";

    foreach ($res as $event) {
        // Buat objek DateTime dari string
        $date = new DateTime($event['created_at'], new DateTimeZone('UTC'));

        // Tambahkan offset GMT+8
        $date->setTimezone(new DateTimeZone('Asia/Singapore')); // Atau Asia/Jakarta
        $event['issues'] = isset($event['issues']) ? $event['issues'] : '';
        $event['commit']['message'] = isset($event['commit']['message']) ? $event['commit']['message'] : '';
        $event['push_data']['ref'] = isset($event['push_data']['ref']) ? $event['push_data']['ref'] : '';

        echo "<tr>";
        echo "<td class='solid'>" . htmlspecialchars($event['id']) . "</td>";
        echo "<td class='solid'>" . htmlspecialchars($event['issues']) . "</td>";
        echo "<td class='solid'>" . htmlspecialchars($event['push_data']['ref']) . "</td>";
        echo "<td class='solid'>" . htmlspecialchars($event['commit']['message']) . "</td>";
        echo "<td class='solid'>";

        foreach ($event['diff'] as $diff) {
            if (isset($diff['new_path'])) {
                echo htmlspecialchars($diff['new_path']) . "<br>";
            }
            if (isset($diff['diff'])) {
                echo htmlspecialchars($diff['diff']);
                // echo "<pre>" . htmlspecialchars($diff['diff']) . "</pre>";
            }
        }

        echo "</td>";
        echo "<td class='solid'>" . htmlspecialchars($date->format('d-m-Y H:i:s')) . "</td>";
        echo "</tr>";
    }

    // Tutup koneksi
    // $conn->close();
    ttd($months[date('m', strtotime('+1 month', strtotime($_GET['start'])))] . " " . date('Y', strtotime($_GET['start'])));
}
function ttd($date = "")
{
    echo '<table style="margin-top:20px;">
    <tr>
        <td class="no-border"></td>
        <td class="no-border" style="padding-left:200px;">Tarakan,&nbsp;&nbsp;&nbsp;&nbsp;' . $date . '</td>
    </tr>
    <tr>
        <td class="no-border" style="padding-left:200px;">Mengetahui,</td>
        <td class="no-border" style="padding-left:200px;">Menyetujui,</td>
    </tr>
    <tr>
        <td class="no-border" style="padding-left:200px;">Kepala Bagian Sekretariat</td>
        <td class="no-border" style="padding-left:200px;">Kepala Instalasi PDE</td>
    </tr>
    <tr style="height:100px;"></tr>
    <tr>
        <td class="no-border" style="padding-left:200px;">ELYANTO, S.E</td>
        <td class="no-border" style="padding-left:200px;">IRMA ULFA, S.Kom</td>
    </tr>
    <tr>
        <td class="no-border" style="padding-left:200px;">NIP. 197310192000121001</td>
        <td class="no-border" style="padding-left:200px;">NIP. 198106242015032003</td>
    </tr>
    <tr style="height:10px;"></tr>
    <tr>
        <td class="no-border" colspan="2" style="padding-left:500px;">Pegawai Yang Membuat,</td>
    </tr>
    <tr style="height:100px;"></tr>
    <tr>
        <td class="no-border" colspan="2" style="padding-left:500px;">' . strtoupper('Dian Rahmadani') . ', S.Kom</td>
    </tr>
    <tr>
        <td class="no-border" colspan="2" style="padding-left:500px;">NRPTT. 272/05/BLUDdrHJSK</td>
    </tr>
    </table>';
}
?>