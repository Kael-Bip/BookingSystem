export async function api<T>(action:string,data?:unknown):Promise<T>{
 let response:Response;
 try{response=await fetch('/staysync-api/index.php?action='+encodeURIComponent(action),{credentials:'include',...(data===undefined?{}:{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})});}
 catch{throw new Error('Cannot reach the StaySync API. Check Apache and open http://localhost/staysync-api/test.php.');}
 const raw=await response.text();let result;
 try{result=JSON.parse(raw);}catch{throw new Error('The StaySync API returned an invalid response (HTTP '+response.status+'). Check Apache/PHP logs.');}
 if(!response.ok)throw new Error(result?.error||'Request failed (HTTP '+response.status+')');return result as T;
}
