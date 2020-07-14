$(function() {
    $('#button').click(function() {
        getArticles(0);
    });
});

function getArticles(page)
{
    var btn = $('#button');

    btn.attr('disabled', 'disabled');
    btn.find('.btn-text').text('Загрузка..');
    btn.find('.spinner-border').removeAttr('hidden');

    $.post('ajax.php', {mod: (page > 0 ? 'articles' : 'parse'), offset: page},
        function(data, status) {

            var jdata = JSON.parse(JSON.stringify(data));

            if (jdata.status == 'success') {
                $('#content, .pagination').empty();
                $.each(jdata.articles, function (index, value) {
                    $('#content').append('<div class="d-flex text-muted pt-3">' +
                        '<p class="pb-3 mb-0 small lh-sm border-bottom">' +
                        '<strong class="d-block text-gray-dark"><a href="#" onClick="return getArticle(' + value.id_article + ');">' + value.name + '</a>' +
                        '</strong>' + value.description + '...</p></div>'
                    );
                });

                let i = 1;
                while (i < jdata.pages+1) {
                    if (page == i) {
                        var append = '<li class="page-item active" aria-current="page"><span class="page-link">' + i + '<span class="sr-only">(current)</span></span></li>';
                    } else {
                        var append = '<li class="page-item"><a class="page-link" href="#" onClick="return getArticles(' + i + ');">' + i + '</a></li>';
                    }
                    $('.pagination').append(append);
                    i++;
                }

                $('#cont').removeAttr('hidden');
            }
            btn.removeAttr('disabled');
            btn.find('.btn-text').text('Загрузить');
            btn.find('.spinner-border').attr('hidden', 'hidden');
        }
    );
    return false;
}

function getArticle(id_article)
{
    $.post('ajax.php', {mod: 'article', id_article: id_article},
        function(data, status) {
            var jdata = JSON.parse(JSON.stringify(data));

            $('#modalLabel').text(jdata.name);
            $('.modal-body').html('<img src="' + jdata.image + '" class="img-fluid"  alt="image" />' + jdata.description);

            var myModal = new bootstrap.Modal(document.getElementById('myModal'), {
                keyboard: false
            });

            myModal.show();
        }
    );
    return false;
}