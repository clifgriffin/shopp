var dp = new DataProtect();
dp.supported = window.localStorage ? true : false;

function DataProtect() {
	this.key = false;
}

function DPstoreKey(psv, prefix) {
	try {
			if(window.localStorage){
					localStorage.setItem(prefix+"shopp_private_key", JSON.stringify(psv));
					return true;
			} else {
				alert(BROWSER_UNSUPPORTED);
				return false;
			}
	} catch (e){
		if (e == 22) {
			alert(LOCAL_STORAGE_QUOTA);
			return true;
		} else {
			alert(LOCAL_STORAGE_ERROR+e.message);
			return true;
		}
	}
}


function DPgetKey(prefix){
	try {
			if(window.localStorage) {
				if(!this.key) {
					privstr = localStorage.getItem(prefix+"shopp_private_key");
					privstr = privstr.replace(/\\([\\'"])/g, '$1').replace(/^(["])|(["])$/g,'');
					if(!privstr) return false;
					psv = JSON.parse(privstr);
					if(!psv) return false;
					else {
						this.key = new RSAKey();
						this.key.setPrivate(psv.n,psv.e,psv.d,psv.p,psv.q,psv.dmp1,psv.dmq1,psv.iqmp);
						return true;
					}
				}	
			} else {
				alert(BROWSER_UNSUPPORTED);
				return false;
			}
	} catch (e){
			alert(LOCAL_STORAGE_ERROR+e.message);
			return false;
	}	
}

function DPdecrypt(encrypted) {
	if(!this.key) return false;
	return this.key.decrypt(encrypted);
}

DataProtect.prototype.store = DPstoreKey;
DataProtect.prototype.get = DPgetKey;
DataProtect.prototype.decrypt = DPdecrypt;

function decrypt (data, prefix) {
	if(!data || (!dp.key && !dp.get(prefix))) { alert(DECRYPTION_ERROR); return false; }
	else {
		var sensitive = JSON.parse(dp.decrypt(data));
		if(!sensitive) {
			alert(DECRYPTION_ERROR);
			jQuery('#card').html(SECRET_DATA);
			jQuery('#cvv').html(SECRET_DATA);
		}
		else {
			jQuery('#card').html(sensitive.card).unbind('click');
			jQuery('#cvv').html(sensitive.cvv).unbind('click');
		}
	}
	return true;
}