/**
 * Global IP Monitor - Admin Panel JavaScript
 */
$(document).ready(function () {

    // DataTable başlat (server-side processing)
    var table = $('#ip_monitor_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: moduleLink + '&action=ajax_logs',
            data: function (d) {
                d.filter_client_id = $('#filter_client_id').val();
                d.filter_ip = $('#filter_ip').val();
                d.filter_date_from = $('#filter_date_from').val();
                d.filter_date_to = $('#filter_date_to').val();
            }
        },
        columns: [
            { title: 'ID', width: '50px' },
            { title: 'Müşteri' },
            { title: 'IP Adresi' },
            { title: 'Port', width: '60px' },
            { title: 'Hostname' },
            { title: 'Giriş Tipi', width: '100px' },
            { title: 'Tarih', width: '140px' },
            { title: 'İşlem', width: '60px', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']], // Tarihe göre son girişler önce
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        language: {
            processing: 'Yükleniyor...',
            search: 'Ara:',
            lengthMenu: 'Sayfada _MENU_ kayıt göster',
            info: '_TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor',
            infoEmpty: 'Kayıt bulunamadı',
            infoFiltered: '(_MAX_ toplam kayıttan filtrelendi)',
            loadingRecords: 'Yükleniyor...',
            zeroRecords: 'Eşleşen kayıt bulunamadı',
            emptyTable: 'Henüz giriş kaydı yok',
            paginate: {
                first: 'İlk',
                previous: 'Önceki',
                next: 'Sonraki',
                last: 'Son'
            }
        },
        responsive: true,
        stateSave: true
    });

    // Filtre butonu
    $('#btn_filter').on('click', function () {
        table.ajax.reload();
    });

    // Sıfırla butonu
    $('#btn_reset').on('click', function () {
        $('#filter_client_id').val('');
        $('#filter_ip').val('');
        $('#filter_date_from').val('');
        $('#filter_date_to').val('');
        table.ajax.reload();
    });

    // Enter tuşu ile filtreleme
    $('.ip-monitor-filters input').on('keypress', function (e) {
        if (e.which === 13) {
            table.ajax.reload();
        }
    });
});
