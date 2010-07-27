/*!
 * mp.js - Shopp ManualProcessing behaviors
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */
var dp = new DataProtect(), bt=true, bf=false, 
	bus=BROWSER_UNSUPPORTED, lse=LOCAL_STORAGE_ERROR, lsq=LOCAL_STORAGE_QUOTA, dde=DATE_DESTRUCTION_ERROR;
dp.supported = window.localStorage ? bt : bf;

function DataProtect() {
	var _ = this;
	_.key = bf;
	
	_.store = function (psv, prefix) {
		try {
				if(window.localStorage){
						localStorage.setItem(prefix+"shopp_private_key", JSON.stringify(psv));
						return bt;
				} else {
					alert(bus);
					return bf;
				}
		} catch (e){
			if (e == 22) {
				alert(lsq);
				return bt;
			} else {
				alert(lse+e.message);
				return bt;
			}
		}
	};


	_.get = function (prefix){
		try {
				if(window.localStorage) {
					if(!_.key) {
						privstr = localStorage.getItem(prefix+"shopp_private_key");
						privstr = privstr.replace(/\\([\\'"])/g, '$1').replace(/^(["])|(["])$/g,'');
						if(!privstr) return bf;
						psv = JSON.parse(privstr);
						if(!psv) return bf;
						else {
							_.key = new RSAKey();
							_.key.setPrivate(psv.n,psv.e,psv.d,psv.p,psv.q,psv.dmp1,psv.dmq1,psv.iqmp);
							return bt;
						}
					}	
				} else {
					alert(bus);
					return bf;
				}
		} catch (e){
				alert(lse+e.message);
				return bf;
		}	
	};

	_.decrypt = function (encrypted) {
		if(!_.key) return bf;
		return _.key.decrypt(encrypted);
	};
}

function decrypt (data, prefix, pid) {
	var $ = jqnc(), card = $('#card'), cvv = $('#cvv'), s=SECRET_DATA, d=DECRYPTION_ERROR,sensitive=false;
	if(!data || (!dp.key && !dp.get(prefix))) { alert(d); return bf; }
	else {
		$.ajax({
			url:sec_card_url,
			timeout:3000,
			type: "POST",
			datatype: 'text',
			data: 'pid='+pid,
			success:function (r) {
				if (r == '1') { 
					sensitive = JSON.parse(dp.decrypt(data));
					if(!sensitive) {
						alert(d);
						card.html(s);
						cvv.html(s);
					}
					else {
						card.html(sensitive.card);
						cvv.html(sensitive.cvv);
						$('#reveal').unbind('click').remove();
					}
				} else alert(dde);
			}			
		});
	}
	return bt;
}