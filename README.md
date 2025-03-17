# MAEMark CF Calculator

## Development

The calculator has been developed within the MAE department at the University of Strathclyde. Problematic cases have been identified with the traditional webPA calculation, where a non-completer can end up with higher grades than they should have despite the non-completion penalty.

## Modified Calculator

The MAEMark CF calculator is based heavily on webPA but applies the following changes:

* A non-completion mask is constructed to identify non-completion based on grades given and not the fractional scores. The mask is 1 for completers and 0 for non-completers. This is applied before the fractional score calculation to also ignore the grades received by the non-completer.

* The fudge factor is ignored.

* The calculator ovverrides the actual grade calculation and exports contributions factors (CFs) in their place, so that these can be handled in Gradebook with greater flexiblity. This means that in the 'Final weighted grade' field in the UI the grader will only see CFs- choosing this calculator entails a concious choice to handle the weighting, cap and penalty in gradebook.
   
## Supported Versions

* This plugin requires the Peer Work module to work. It has been tested with Peer Work v4.5.0 and Moodle versions 4.0.
