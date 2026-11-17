import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PageComponent } from './pages/page.component';
const routes:Routes=[{path:'',redirectTo:'landing',pathMatch:'full'},...['landing','login','register','home','services','booking-form','booking-summary','payment','my-bookings','payments','profile','about'].map(path=>({path,component:PageComponent})),{path:'**',redirectTo:'landing'}];
@NgModule({imports:[RouterModule.forRoot(routes)],exports:[RouterModule]})export class AppRoutingModule{}
