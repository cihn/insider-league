import { Component } from '../lib/Component.js';
import { el } from '../lib/dom.js';

/**
 * Step 2: show the generated week-by-week fixtures before kicking off.
 * Three cards per row on desktop (Bootstrap col-md-4).
 */
export class FixturesView extends Component {
    render() {
        const { fixtures, onStart } = this.props;

        const columns = fixtures.map((week) => el('div', { class: 'col-12 col-sm-6 col-md-4' },
            el('div', { class: 'card shadow-sm h-100' },
                el('div', { class: 'card-header bg-dark text-white fw-semibold' }, `Week ${week.week}`),
                el('ul', { class: 'list-group list-group-flush' },
                    ...week.matches.map((match) => el('li', { class: 'list-group-item' },
                        el('div', { class: 'd-flex align-items-center gap-2' },
                            el('span', { class: 'flex-fill text-end' }, match.home_team),
                            el('span', { class: 'text-muted text-center', style: 'min-width:24px' }, '-'),
                            el('span', { class: 'flex-fill text-start' }, match.away_team),
                        ),
                    )),
                ),
            ),
        ));

        return el('section', {},
            el('h1', { class: 'h3 text-center fw-light text-secondary mb-4' }, 'Generated Fixtures'),
            el('div', { class: 'row g-4' }, ...columns),
            el('div', { class: 'text-center mt-4' },
                el('button', { class: 'btn btn-primary btn-lg', onClick: onStart }, 'Start Simulation'),
            ),
        );
    }
}
