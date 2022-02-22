/**
 * Block Editor Plugin for Exclusive Taxonomy
 * Based on https://wordpress.org/plugins/radio-buttons-for-taxonomies/
 *
 * @author Takuto Yanagida
 * @version 2022-02-22
 */

((wp) => {
	const {
		i18n      : { __ },
		data      : { withSelect, withDispatch },
		components: { CheckboxControl, RadioControl, withSpokenMessages },
		compose   : { withInstanceId, compose },
		element   : { Component, createElement },
		url       : { addQueryArgs },
		apiFetch
	} = wp;
	const el = createElement;

	class CustomTermSelector extends Component {

		constructor() {
			super(...arguments);
			this.onChange = this.onChange.bind(this);
			this.state    = { availableTermsTree: [] };
		}

		onChange(termId) {
			const { onUpdateTerms, taxonomy, terms, isExclusive } = this.props;
			if (isExclusive) {
				onUpdateTerms([termId], taxonomy.rest_base);
			} else {
				const hasTerm  = terms.includes(termId);
				const newTerms = hasTerm ? _.without(terms, termId) : [...terms, termId];
				onUpdateTerms(newTerms, taxonomy.rest_base);
			}
		}

		onClear() {
			const { onUpdateTerms, taxonomy } = this.props;
			onUpdateTerms([], taxonomy.rest_base);
		}

		componentDidMount() {
			this.fetchTerms();
		}

		componentWillUnmount() {
			_.invoke(this.fetchRequest, ['abort']);
		}

		componentDidUpdate(prevProps) {
			if (this.props.taxonomy !== prevProps.taxonomy) {
				this.fetchTerms();
			}
		}

		fetchTerms() {
			const { taxonomy } = this.props;
			if (!taxonomy) return;

			this.fetchRequest = apiFetch({
				path: addQueryArgs(`/wp/v2/${taxonomy.rest_base}`, {
					per_page: -1,
					orderby : 'name',
					order   : 'asc',
					_fields : 'id,name,parent',
				}),
			});
			this.fetchRequest.then(
				(terms) => {
					this.fetchRequest = null;
					this.setState({
						availableTermsTree: buildTermsTree(terms),
					});
				}, (xhr) => {
					if (xhr.statusText === 'abort') return;
					this.fetchRequest = null;
				}
			);
		}

		render() {
			const { hasAssignAction, taxonomy } = this.props;
			if (!hasAssignAction) return null;

			return el(
				'div',
				{
					className: `editor-post-taxonomies__hierarchical-terms-list${taxonomy.hierarchical ? '' : ' non-hierarchical'}`,
					key      : 'term-list',
					tabIndex : '0',
					role     : 'group',
					ariaLabel: taxonomy.name ?? __('Terms'),
				},
				this.renderTerms(this.state.availableTermsTree)
			);
		}

		renderTerms(renderedTerms) {
			const { terms = [], taxonomy, isExclusive } = this.props;

			return renderedTerms.map((term) => {
				const sel  = (-1 !== terms.indexOf(term.id) || (!terms.length && term.id === taxonomy.default_term)) ? term.id : 0;
				const comp = this.createComponent(term, sel, isExclusive);
				return el(
					'div',
					{ className: 'editor-post-taxonomies__hierarchical-terms-choice', key: term.id },
					[
						comp,
						term.children.length ? el(
							'div',
							{ className: 'editor-post-taxonomies__hierarchical-terms-subchoices' },
							this.renderTerms(term.children)
						) : null
					]
				);
			});
		}

		createComponent(term, sel, isExclusive) {
			if (isExclusive) {
				return el(
					RadioControl,
					{
						options : [{ label: term.name, value: term.id }],
						selected: sel,
						onChange: () => { this.onChange(parseInt(term.id, 10)); },
					}
				);
			}
			return el(
				CheckboxControl,
				{
					label   : term.name,
					checked : sel,
					onChange: () => { this.onChange(parseInt(term.id, 10)); },
				}
			);
		}

	}

	const ExTxComp = compose( [
		withSelect((select, { slug }) => {
			const taxonomy = select('core').getTaxonomy( slug );
			if (!taxonomy) return { hasAssignAction: false, terms: [], taxonomy, isExclusive: false };

			const wct_ex      = wpinc_custom_taxonomy_exclusive;
			const isExclusive = (wct_ex && ('*' === wct_ex || wct_ex.includes(slug)));

			const { getCurrentPost, getEditedPostAttribute } = select('core/editor');
			return {
				hasAssignAction: _.get(getCurrentPost(), ['_links', `wp:action-assign-${taxonomy.rest_base}`], false),
				terms          : getEditedPostAttribute(taxonomy.rest_base),
				taxonomy,
				isExclusive,
			};
		} ),
		withDispatch((dispatch) => ({
			onUpdateTerms(terms, restBase) {
				dispatch('core/editor').editPost({ [restBase]: terms });
			},
		})),
		withSpokenMessages,
		withInstanceId,
	])(CustomTermSelector);


	// -----------------------------------------------------------------------------


	function buildTermsTree(flatTerms) {
		const ts   = flatTerms.map(t => ({ ...t, children: [] }));
		const p2ts = _.groupBy(ts, 'parent');
		if (p2ts[undefined] && p2ts[undefined].length) return ts;

		const setChildren = ts => ts.map(t => {
			const cs = p2ts[t.id] ?? [];
			return {
				...t,
				children: cs.length ? setChildren(cs) : cs,
			};
		});
		return setChildren(p2ts[0] || []);
	}


	// -----------------------------------------------------------------------------


	function Filter(OrigComp) {
		const wct_in = wpinc_custom_taxonomy_inclusive;
		const wct_ex = wpinc_custom_taxonomy_exclusive;

		return function (props) {
			const tx = props.slug;
			if (
				(wct_in && ('*' === wct_in || wct_in.includes(tx))) ||
				(wct_ex && ('*' === wct_ex || wct_ex.includes(tx)))
			) {
				return wp.element.createElement(ExTxComp, props);
			}
			return wp.element.createElement(OrigComp, props);
		}
	};

	wp.hooks.addFilter(
		'editor.PostTaxonomyType',
		'custom-taxonomy',
		Filter
	);
})(window.wp);
