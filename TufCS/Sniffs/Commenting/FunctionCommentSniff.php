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
     * {@inheritdoc}
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

        // Don't check the @return of constructors or destructors.
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        $isSpecialMethod = ($methodName === '__construct' || $methodName === '__destruct');
        if ($isSpecialMethod) {
            return;
        }

        // Find the return tag. The parent method will ensure that it is
        // present and unique.
        $return = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                $return = $tag;
            }
        }

        // Copied from the parent sniff to parse out the return type.
        $content = $tokens[($return + 2)]['content'];
        preg_match('`^((?:\|?(?:array\([^\)]*\)|[\\\\a-z0-9\[\]]+))*)( .*)?`i', $content, $returnParts);

        // The parent sniff will raise errors if the return type is missing or
        // malformatted.
        $returnType = $returnParts[1] ?? null;

        // if the return type is 'void', skip checking for a @return comment.
        if ($returnType !== 'void') {
            $this->checkForTagComment($phpcsFile, $stackPtr, $commentStart, '@return');
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

        // Check for a comment for the @throws tag.
        // Squiz.Commenting.FunctionComment.EmptyThrows doesn't work with our
        // newline format.
        $this->checkForTagComment($phpcsFile, $stackPtr, $commentStart, '@throws');

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

        // Check for a comment for the parameter tag.
        // Squiz.Commenting.FunctionComment.MissingParamComment doesn't work
        // with our newline format.
        $this->checkForTagComment($phpcsFile, $stackPtr, $commentStart, '@param');

        return parent::processParams($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * Determines whether the whole comment is an inheritdoc comment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     *     The file being scanned.
     * @param int $stackPtr
     *     The position of the current token in the stack passed in $tokens.
     * @param int $commentStart
     *     The position in the stack where the comment started.
     *
     * @return bool
     *     TRUE if the docblock contains only {@inheritdoc} (case-insensitive),
     *     or FALSE otherwise.
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

    /**
     * Checks for a comment explaining a docblock tag.
     *
     * The parent sniff doesn't detect @param, @return, or @throws
     * documentation on a subsequent line of the docblock, which we use for
     * readability and better compliance with PSR-2 line length
     * recommendations. E.g.:
     *
     * @code
     *     * @param type $name
     *     *     Comment explaining the parameter here.
     * @endcode
     *
     * This method provides an alternate check for a missing comment with an
     * alternate error.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     *     The file being scanned.
     * @param int $stackPtr
     *     The position of the current token in the stack passed in $tokens.
     * @param int $commentStart
     *     The position in the stack where the comment started.
     * @param string $tagType
     *     (optional) The tag type: '@param', '@return', or '@throws'. Defaults
     *     to '@param'.
     *
     * @return void
     */
    protected function checkForTagComment(File $phpcsFile, int $stackPtr, int $commentStart, string $tagType = '@param')
    {
        $tokens = $phpcsFile->getTokens();

        // Construct an error type based on the specified tag.
        $errorType = 'Missing' . ucfirst(substr($tagType, 1)) . 'CommentOnNewline';

        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $startOfTag) {
            if ($tokens[$startOfTag]['content'] !== $tagType) {
                // Only check the given tag type.
                continue;
            }

            // Unlike the parent sniff's @tag comment format, our comment
            // format doesn't have a single string with the type and parameter
            // name, since it's on a newline and after an additional '*'. So,
            // instead, search ahead for the next docblock tag and look for
            // anything in between. The tag might also be the last tag in the
            // docblock, so stop at the comment end if that comes first.
            $endOfTag = $tokens[$commentStart]['comment_tags'][$pos + 1] ?? $tokens[$commentStart]['comment_closer'];

            // Find the comment string(s), if any.
            $tagComment = [];

            // Search ahead.
            for ($i = $startOfTag; $i < $endOfTag; $i++) {
                if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                    $tagComment[]= $tokens[$i]['content'];
                }
            }

            // For @param, the first element of the array should contain the
            // parameter type and name, e.g. 'string $paramName'.
            // For @return and @throws, it should contain just the data type.
            // Other sniffs will detect if any of that is malformed. Shift it
            // off our content.
            array_unshift($tagComment);

            // The remaining elements of the array contain any documentation
            // for the parameter. Throw an error if there isn't anything.
            if (empty($tagComment)) {
                $phpcsFile->addError("Missing comment documenting $tagType", $stackPtr, $errorType);
            }
        }
    }
}
