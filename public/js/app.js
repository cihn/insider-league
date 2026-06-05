import { ApiClient } from './lib/ApiClient.js';
import { mount, el } from './lib/dom.js';
import { TeamsView } from './components/TeamsView.js';
import { FixturesView } from './components/FixturesView.js';
import { SimulationView } from './components/SimulationView.js';

/**
 * Front-end controller: owns the application state, decides which view to
 * show, wires component callbacks to API calls and re-renders on every change.
 */
class App {
    constructor(host) {
        this.host = host;
        this.api = new ApiClient();
        this.state = null;
        this.view = 'teams';
        this.selectedWeek = 1;
        this.busy = false;
    }

    async init() {
        try {
            this.state = await this.api.state();
            this.view = this.state.has_fixtures ? 'simulation' : 'teams';
            this.selectedWeek = this.latestActiveWeek();
            this.render();
        } catch (error) {
            this.showError(error);
        }
    }

    /**
     * Run an async mutation with a busy guard, error handling and a re-render.
     */
    async perform(mutator) {
        if (this.busy) {
            return;
        }

        this.busy = true;
        this.host.classList.add('opacity-50', 'pe-none');

        try {
            await mutator();
        } catch (error) {
            this.showError(error);
        } finally {
            this.busy = false;
            this.host.classList.remove('opacity-50', 'pe-none');
            this.render();
        }
    }

    handlers() {
        return {
            onGenerate: () => this.perform(async () => {
                this.state = await this.api.generateFixtures();
                this.view = 'fixtures';
                this.selectedWeek = 1;
            }),
            onStart: () => {
                this.view = 'simulation';
                this.selectedWeek = this.latestActiveWeek();
                this.render();
            },
            onNext: () => this.perform(async () => {
                this.state = await this.api.playNextWeek();
                this.selectedWeek = this.latestActiveWeek();
            }),
            onAll: () => this.perform(async () => {
                this.state = await this.api.playAllWeeks();
                this.selectedWeek = this.state.total_weeks || 1;
            }),
            onReset: () => this.perform(async () => {
                this.state = await this.api.reset();
                this.view = 'teams';
                this.selectedWeek = 1;
            }),
            onEdit: (matchId, homeGoals, awayGoals) => this.perform(async () => {
                this.state = await this.api.updateMatch(matchId, homeGoals, awayGoals);
            }),
            onChangeWeek: (week) => {
                this.selectedWeek = week;
                this.render();
            },
        };
    }

    /**
     * The most advanced week worth showing: the last one with a played match,
     * or week 1 when nothing has been played yet.
     */
    latestActiveWeek() {
        const playedWeeks = (this.state?.fixtures ?? [])
            .filter((week) => week.matches.some((match) => match.played))
            .map((week) => week.week);

        return playedWeeks.length > 0 ? Math.max(...playedWeeks) : 1;
    }

    render() {
        const handlers = this.handlers();
        let view;

        if (this.view === 'teams') {
            view = new TeamsView({ teams: this.state.teams, onGenerate: handlers.onGenerate });
        } else if (this.view === 'fixtures') {
            view = new FixturesView({ fixtures: this.state.fixtures, onStart: handlers.onStart });
        } else {
            view = new SimulationView({
                state: this.state,
                selectedWeek: this.selectedWeek,
                onChangeWeek: handlers.onChangeWeek,
                onNext: handlers.onNext,
                onAll: handlers.onAll,
                onReset: handlers.onReset,
                onEdit: handlers.onEdit,
            });
        }

        mount(this.host, view.render());
    }

    showError(error) {
        const toast = el('div', {
            class: 'alert alert-danger position-fixed bottom-0 start-50 translate-middle-x shadow mb-4',
            role: 'alert',
        }, error?.message ?? 'Something went wrong.');
        document.body.append(toast);
        setTimeout(() => toast.remove(), 3500);
    }
}

const host = document.getElementById('app');
new App(host).init();
