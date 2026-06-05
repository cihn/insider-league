import { Component } from '../lib/Component.js';
import { el } from '../lib/dom.js';
import { LeagueTable } from './LeagueTable.js';
import { WeekResults } from './WeekResults.js';
import { Predictions } from './Predictions.js';

/**
 * Step 3: the live simulation screen — standings, the selected week's results
 * and the championship predictions, plus the play/reset controls.
 */
export class SimulationView extends Component {
    render() {
        const { state, selectedWeek, onChangeWeek, onNext, onAll, onReset, onEdit } = this.props;

        const selectedFixtures = state.fixtures.find((week) => week.week === selectedWeek);

        const leagueTable = new LeagueTable({ table: state.table }).render();

        const weekResults = new WeekResults({
            week: selectedWeek,
            totalWeeks: state.total_weeks,
            matches: selectedFixtures ? selectedFixtures.matches : [],
            onChangeWeek,
            onEdit,
        }).render();

        const predictions = new Predictions({
            predictions: this.namedPredictions(state),
            hasPlayed: state.table.some((row) => row.played > 0),
            status: state.predictions_status,
        }).render();

        return el('section', {},
            el('h1', { class: 'h3 text-center fw-light text-secondary mb-4' }, 'Simulation'),
            el('div', { class: 'row g-4' },
                el('div', { class: 'col-12 col-lg-6' }, leagueTable),
                el('div', { class: 'col-12 col-md-6 col-lg-3' }, weekResults),
                el('div', { class: 'col-12 col-md-6 col-lg-3' }, predictions),
            ),
            el('div', { class: 'd-flex justify-content-center flex-wrap gap-3 mt-4' },
                el('button', { class: 'btn btn-primary', disabled: state.is_complete, onClick: onAll }, 'Play All Weeks'),
                el('button', { class: 'btn btn-primary', disabled: state.is_complete, onClick: onNext }, 'Play Next Week'),
                el('button', { class: 'btn btn-danger', onClick: onReset }, 'Reset Data'),
            ),
        );
    }

    /**
     * Join prediction percentages (keyed by team id) with team names.
     */
    namedPredictions(state) {
        return state.teams.map((team) => ({
            name: team.name,
            percentage: state.predictions[team.id] ?? 0,
        }));
    }
}
