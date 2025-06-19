<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Enum;

enum HistorySeverityEnum: string
{
    case LOW = 'low'; // low is used for the most common informative logs in history
    case MEDIUM = 'medium'; // medium is used for more important logs that are created more rarely than average change
    case HIGH = 'high'; // high is used for destructive actions or rare business use cases or errors that happened and change was impossible
}
