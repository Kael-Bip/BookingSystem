import { Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import { api } from './api';
export interface Account {id:number;name:string;email:string;}
@Injectable({providedIn:'root'}) export class AuthService {
 private account=signal<Account|null>(null);
 get current(){return this.account();}
 set current(value:Account|null){this.account.set(value);}
 ready:Promise<void>;
 constructor(private router:Router){this.ready=api<Account|null>('me').then(u=>{this.current=u;}).catch(()=>{});}
 async register(name:string,email:string,password:string){this.current=await api<Account>('register',{name,email,password});}
 async login(email:string,password:string){this.current=await api<Account>('login',{email,password});}
 async logout(){try{await api('logout',{});this.current=null;this.router.navigateByUrl('/landing');}catch{alert('Unable to log out. Check XAMPP.');}}
}
