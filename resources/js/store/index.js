import Vue from 'vue';
import Vuex from 'vuex';
import auth from './auth';
import records from './records';
import dashboard from './dashboard';

Vue.use(Vuex);
export default new Vuex.Store({ strict: import.meta.env.DEV, modules: { auth, records, dashboard } });
