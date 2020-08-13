<?php

namespace TufCS\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\FunctionCommentSniff as SquizFunctionCommentSniff;

class FunctionCommentSniff extends SquizFunctionCommentSniff
{

    /**
     * Whether to skip inheritdoc comments.
     *
     * @var boolean
     */
    public $skipIfInheritdoc = false;

    /**
     * {@inheridoc}
     */
    protected function processReturn(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Add support for the public property to skip {@inheritdoc} docblocks.
        // @todo This can be removed if it gets merged upstream.
        //     https://github.com/squizlabs/PHP_CodeSniffer/pull/3051
        if ($this->skipIfInheritdoc === true) {
            if ($this->checkInheritdoc($phpcsFile, $stackPtr, $commentStart) === true) {
                return;
            }
        }

        return parent::processReturn($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * {@inheritdoc}
     */
    protected function processThrows(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Add support for the public property to skip {@inheritdoc} docblocks.
        // @todo This can be removed if it gets merged upstream.
        //     https://github.com/squizlabs/PHP_CodeSniffer/pull/3051
        if ($this->skipIfInheritdoc === true) {
            if ($this->checkInheritdoc($phpcsFile, $stackPtr, $commentStart) === true) {
                return;
            }
        }

        return parent::processThrows($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * {@inheritdoc}
     */
    protected function processParams(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Add support for the public property to skip {@inheritdoc} docblocks.
        // @todo This can be removed if it gets merged upstream.
        //     https://github.com/squizlabs/PHP_CodeSniffer/pull/3051
        if ($this->skipIfInheritdoc === true) {
            if ($this->checkInheritdoc($phpcsFile, $stackPtr, $commentStart) === true) {
                return;
            }
        }

        // The parent sniff doesn't detect parameter documentation on a
        // subsequent line of the docblock, which we use for readability
        // and better compliance with PSR-2 line length recommendations.
        // E.g.:
        // /**
        //  * @param type $name
        //  *     Comment explaining the parameter here.
        //  */
        //  Our ruleset XML therefore skips the
        //  TufCS.Commenting.FunctionComment.MissingParamComment error.
        //  Therefore, we need to raise our own error instead.
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@param') {
                // We're only checking paramter tag comments for now.
                continue;
            }

            // Unlike the parent sniff, our comment isn't in a single string
            // with the type and parameter name, since it's on a newline and
            // after an additional '*'. So, instead, search ahead for the next
            // docblock tag and look for anything in between.
            // This might also be the last tag in the docblock, so stop at the
            // comment end if that comes first.
            $endOfTag = $tokens[$commentStart]['comment_tags'][$pos + 1] ?? $tokens[$commentStart]['comment_closer'];

            // Find the comment string(s), if any.
            $tagComment = [];

            // Search ahead.
            // @todo The +3 magic number assumes a param type and name before
            //     the comment. Will the sniff give a false error if they are
            //     missing? Somehow it seems to work even for missing param
            //     types and names currently.
            for ($i = $tokens[$commentStart]['comment_tags'][$pos] + 3; $i < $endOfTag; $i++) {
                if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                    $tagComment[]= $tokens[$i]['content'];
                }
            }
            if (empty($tagComment)) {
                $phpcsFile->addError('Missing parameter comment', $stackPtr, 'MissingParamCommentOnNewline');
            }
        }

        return parent::processParams($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * Check the spacing after the type of a parameter.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $param     The parameter to be checked.
     * @param int                         $maxType   The maxlength of the longest parameter type.
     * @param int                         $spacing   The number of spaces to add after the type.
     *
     * @return void
     */
    protected function checkSpacingAfterParamType(File $phpcsFile, $param, $maxType, $spacing=1)
    {
        // Check number of spaces after the type.
        $spaces = ($maxType - strlen($param['type']) + $spacing);
        if ($param['type_space'] !== $spaces) {
            $error = 'Expected %s spaces after parameter type; %s found';
            $data  = [
                $spaces,
                $param['type_space'],
            ];

            $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamType', $data);
            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();

                $content  = $param['type'];
                $content .= str_repeat(' ', $spaces);
                $content .= $param['var'];
                $content .= str_repeat(' ', $param['var_space']);
                $content .= $param['commentLines'][0]['comment'];
                $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                // Fix up the indent of additional comment lines.
                $diff = ($param['type_space'] - $spaces);
                foreach ($param['commentLines'] as $lineNum => $line) {
                    if ($lineNum === 0
                        || $param['commentLines'][$lineNum]['indent'] === 0
                    ) {
                        continue;
                    }

                    $newIndent = ($param['commentLines'][$lineNum]['indent'] - $diff);
                    if ($newIndent <= 0) {
                        continue;
                    }

                    $phpcsFile->fixer->replaceToken(
                        ($param['commentLines'][$lineNum]['token'] - 1),
                        str_repeat(' ', $newIndent)
                    );
                }

                $phpcsFile->fixer->endChangeset();
            }//end if
        }//end if

    }//end checkSpacingAfterParamType()


    /**
     * Check the spacing after the name of a parameter.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $param     The parameter to be checked.
     * @param int                         $maxVar    The maxlength of the longest parameter name.
     * @param int                         $spacing   The number of spaces to add after the type.
     *
     * @return void
     */
    protected function checkSpacingAfterParamName(File $phpcsFile, $param, $maxVar, $spacing=1)
    {
        // Check number of spaces after the var name.
        $spaces = ($maxVar - strlen($param['var']) + $spacing);
        if ($param['var_space'] !== $spaces) {
            $error = 'Expected %s spaces after parameter name; %s found';
            $data  = [
                $spaces,
                $param['var_space'],
            ];

            $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamName', $data);
            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();

                $content  = $param['type'];
                $content .= str_repeat(' ', $param['type_space']);
                $content .= $param['var'];
                $content .= str_repeat(' ', $spaces);
                $content .= $param['commentLines'][0]['comment'];
                $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                // Fix up the indent of additional comment lines.
                foreach ($param['commentLines'] as $lineNum => $line) {
                    if ($lineNum === 0
                        || $param['commentLines'][$lineNum]['indent'] === 0
                    ) {
                        continue;
                    }

                    $diff      = ($param['var_space'] - $spaces);
                    $newIndent = ($param['commentLines'][$lineNum]['indent'] - $diff);
                    if ($newIndent <= 0) {
                        continue;
                    }

                    $phpcsFile->fixer->replaceToken(
                        ($param['commentLines'][$lineNum]['token'] - 1),
                        str_repeat(' ', $newIndent)
                    );
                }

                $phpcsFile->fixer->endChangeset();
            }//end if
        }//end if

    }//end checkSpacingAfterParamName()


    /**
     * Determines whether the whole comment is an inheritdoc comment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     *     The file being scanned.
     * @param int $stackPtr
     *     The position of the current token in the stack passed in $tokens.
     * @param int  $commentStart
     *     The position in the stack where the comment started.
     *
     * @return void
     */
    protected function checkInheritdoc(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $allowedTokens = [
            T_DOC_COMMENT_OPEN_TAG,
            T_DOC_COMMENT_WHITESPACE,
            T_DOC_COMMENT_STAR,
        ];
        for ($i = $commentStart; $i <= $tokens[$commentStart]['comment_closer']; $i++) {
            if (in_array($tokens[$i]['code'], $allowedTokens) === false) {
                $trimmedContent = strtolower(trim($tokens[$i]['content']));

                if ($trimmedContent === '{@inheritdoc}') {
                    return true;
                } else {
                    return false;
                }
            }
        }
    }
}
