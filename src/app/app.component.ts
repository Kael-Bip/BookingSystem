import { Component } from '@angular/core';
import { AuthService } from './services/auth.service';
@Component({selector:'app-root',templateUrl:'app.component.html',styleUrls:['app.component.scss'],standalone:false})
export class AppComponent {constructor(public auth:AuthService){} menu=[{title:'Home',url:'/home',icon:'home-outline'},{title:'Browse Services',url:'/services',icon:'bed-outline'},{title:'My Bookings',url:'/my-bookings',icon:'calendar-outline'},{title:'Payments',url:'/payments',icon:'card-outline'},{title:'Profile',url:'/profile',icon:'person-outline'},{title:'About',url:'/about',icon:'information-circle-outline'}];}
