(function($){
    if (typeof KCUserGen === 'undefined') return;

    const len = KCUserGen.length || 20;
    // Alleen letters/cijfers, zonder i I l L
    const charset = KCUserGen.charset || 'ABCDEFGHJKMNOPQRSTUVWXYZabcdefghjkmnopqrstuvwxyz0123456789';

    function randStr(n){
        let out = '';
        const cryptoObj = window.crypto || window.msCrypto;
        if (cryptoObj && cryptoObj.getRandomValues) {
            const buf = new Uint8Array(n);
            cryptoObj.getRandomValues(buf);
            for (let i=0;i<n;i++){
                out += charset[ buf[i] % charset.length ];
            }
            return out;
        }
        for (let i=0;i<n;i++) out += charset.charAt(Math.floor(Math.random()*charset.length));
        return out;
    }

    function ensurePasswordRow(){
        // Als WP geen pass1/2 toont op multisite, voeg eigen rij toe met kc_pass1
        let $pw = $('#pass1');
        if ($pw.length) return; // WP heeft al password UI

        const $userRow = $('#user_login').closest('tr');
        if (!$userRow.length) return;

        const $row = $(
            '<tr class="form-field kc-pass-row">' +
                '<th scope="row"><label for="kc_pass1">Wachtwoord</label></th>' +
                '<td>' +
                    '<input type="text" id="kc_pass1" name="kc_pass1" class="regular-text" autocomplete="off" /> ' +
                    '<button type="button" class="button kc-gen-pass-btn">Genereer wachtwoord</button>' +
                '</td>' +
            '</tr>'
        );
        $row.insertAfter($userRow);

        $row.on('click', '.kc-gen-pass-btn', function(){
            $('#kc_pass1').val(randStr(len));
        });
    }

    function injectUI(){
        const $form = $('#createuser');
        if (!$form.length) return;

        // Knop: genereer gebruikersnaam + dummy e-mail live
        const $userRow = $('#user_login').closest('tr');
        if ($userRow.length && $userRow.find('.kc-gen-group').length === 0) {
            const $wrap = $('<div class="kc-gen-group" style="margin-top:6px;"></div>');
            const $btn = $('<button type="button" class="button">Genereer gebruikersnaam</button>');
            $btn.on('click', function(){
                const v = randStr(len);
                $('#user_login').val(v);
                $('#email').val(v + '@' + KCUserGen.dummyDom);
            });
            $wrap.append($btn);
            $userRow.find('td').append($wrap);
        }

        // Zorg voor wachtwoord-invoer
        ensurePasswordRow();

        // Als WP wel pass1 heeft, maak type=text en voeg generator ernaast
        let $pw = $('#pass1');
        if ($pw.length) {
            $pw.attr('type','text');
            $('#pass2').attr('type','text');
            if ($pw.closest('td').find('.kc-gen-pass').length===0) {
                const $wrap2 = $('<div class="kc-gen-pass" style="margin-top:6px;"></div>');
                const $btn2 = $('<button type="button" class="button">Genereer wachtwoord</button>');
                $btn2.on('click', function(){
                    const v = randStr(len);
                    $('#pass1, #pass2, #pass1-text').val(v);
                });
                $wrap2.append($btn2);
                $pw.closest('td').append($wrap2);
            }
        }

        // Submit: forceer dummy e-mail en mirror velden
        $form.on('submit', function(){
            const u = $('#user_login').val();
            if ($('#email').length) {
                $('#email').val(u + '@' + KCUserGen.dummyDom);
            } else {
                $('<input type="hidden" name="email">').val(u + '@' + KCUserGen.dummyDom).appendTo($form);
            }
            const p = $('#pass1').length ? $('#pass1').val() : $('#kc_pass1').val();
            if ($('input[name="kc_pass1"]').length===0) {
                $('<input type="hidden" name="kc_pass1">').val(p).appendTo($form);
            } else {
                $('input[name="kc_pass1"]').val(p);
            }
            if ($('input[name="kc_user_login"]').length===0) {
                $('<input type="hidden" name="kc_user_login">').val(u).appendTo($form);
            } else {
                $('input[name="kc_user_login"]').val(u);
            }
        });
    }

    $(document).ready(injectUI);
})(jQuery);
